<?php

namespace App\Tests\Functional\Tool;

use App\Entity\Tool;
use App\Entity\ToolStatus;
use App\Entity\User;
use App\Repository\ToolRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ToolCrudTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $user = (new User())->setEmail('tools'.uniqid().'@example.com')->setPassword('not-used');
        $em->persist($user);
        $em->flush();
        $this->client->loginUser($user);
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/tools');

        $this->assertResponseRedirects('/login');
    }

    public function testIndexListsToolsAndHasNavLink(): void
    {
        $tool = $this->createTool();

        $this->client->request('GET', '/tools', ['q' => $tool->getSerialNumber()]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Tools');
        $this->assertSelectorTextContains('table', $tool->getName());
        $this->assertSelectorTextContains('table', '12.50 €');
        $this->assertSelectorExists('nav a[href="/tools"]');
    }

    public function testIndexSearchesByNameAndSerialNumber(): void
    {
        $tool = $this->createTool();
        $other = $this->createTool();

        $this->client->request('GET', '/tools', ['q' => strtoupper($tool->getName())]);
        $this->assertSelectorTextContains('table', $tool->getName());
        $this->assertSelectorTextNotContains('table', $other->getName());

        $this->client->request('GET', '/tools', ['q' => $other->getSerialNumber()]);
        $this->assertSelectorTextContains('table', $other->getName());
        $this->assertSelectorTextNotContains('table', $tool->getName());
    }

    public function testIndexFiltersByStatus(): void
    {
        $marker = 'Filter'.uniqid();
        $available = $this->createTool(name: $marker.'a');
        $retired = $this->createTool(ToolStatus::Retired, $marker.'r');

        $this->client->request('GET', '/tools', ['status' => 'retired', 'q' => $marker]);

        $this->assertSelectorTextContains('table', $retired->getName());
        $this->assertSelectorTextNotContains('table', $available->getName());
    }

    public function testIndexShowsEmptyStateWhenNothingMatches(): void
    {
        $this->client->request('GET', '/tools', ['q' => 'no-such-tool-'.uniqid()]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'No tools match your search.');
        $this->assertSelectorNotExists('table');
    }

    public function testIndexIgnoresInvalidSortStatusAndDirection(): void
    {
        $this->client->request('GET', '/tools', ['sort' => 'password; DROP TABLE tools', 'dir' => 'sideways', 'status' => 'bogus']);

        $this->assertResponseIsSuccessful();
    }

    public function testIndexPaginates(): void
    {
        $marker = 'Paged'.uniqid();
        for ($i = 0; $i < 11; ++$i) {
            $this->createTool(name: $marker.$i);
        }

        $this->client->request('GET', '/tools', ['q' => $marker]);

        $this->assertSelectorExists('nav[aria-label="Pagination"] a[rel="next"]');
    }

    public function testCreateToolWithValidData(): void
    {
        $serial = 'SN-'.uniqid();
        $crawler = $this->client->request('GET', '/tools/new');
        $this->assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Create tool')->form([
            'tool[name]' => 'Hammer Drill',
            'tool[serialNumber]' => $serial,
            'tool[imageUrl]' => 'https://example.com/drill.jpg',
            'tool[dailyRentCost]' => '19.9',
            'tool[description]' => 'Heavy duty',
        ]));

        $this->assertResponseRedirects('/tools');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[role="status"]', 'Hammer Drill');

        $tool = $this->findBySerial($serial);
        $this->assertNotNull($tool);
        $this->assertSame('19.90', $tool->getDailyRentCost());
        $this->assertSame(ToolStatus::Available, $tool->getStatus());
        $this->assertNull($tool->getComments());
    }

    public function testCreateToolRejectsMissingRequiredFields(): void
    {
        $crawler = $this->client->request('GET', '/tools/new');
        $this->client->submit($crawler->selectButton('Create tool')->form());

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('.form-error[role="alert"]');
    }

    public function testCreateToolRejectsNegativeCost(): void
    {
        $serial = 'SN-'.uniqid();
        $crawler = $this->client->request('GET', '/tools/new');
        $form = $crawler->selectButton('Create tool')->form([
            'tool[name]' => 'Saw',
            'tool[serialNumber]' => $serial,
            'tool[imageUrl]' => 'https://example.com/saw.jpg',
        ]);
        $form->setValues(['tool[dailyRentCost]' => '-5']);
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertNull($this->findBySerial($serial));
    }

    public function testCreateToolRejectsInvalidImageUrl(): void
    {
        $serial = 'SN-'.uniqid();
        $crawler = $this->client->request('GET', '/tools/new');
        $this->client->submit($crawler->selectButton('Create tool')->form([
            'tool[name]' => 'Saw',
            'tool[serialNumber]' => $serial,
            'tool[imageUrl]' => 'not a url',
            'tool[dailyRentCost]' => '5',
        ]));

        $this->assertResponseStatusCodeSame(422);
        $this->assertNull($this->findBySerial($serial));
    }

    public function testCreateToolRejectsDuplicateSerialNumber(): void
    {
        $existing = $this->createTool();
        $crawler = $this->client->request('GET', '/tools/new');
        $this->client->submit($crawler->selectButton('Create tool')->form([
            'tool[name]' => 'Another',
            'tool[serialNumber]' => $existing->getSerialNumber(),
            'tool[imageUrl]' => 'https://example.com/a.jpg',
            'tool[dailyRentCost]' => '5',
        ]));

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.form-error', 'Serial number is already in use');
    }

    public function testStatusFieldCannotBeSetToRented(): void
    {
        $serial = 'SN-'.uniqid();
        $crawler = $this->client->request('GET', '/tools/new');
        $this->client->request('POST', '/tools/new', ['tool' => [
            'name' => 'Saw',
            'serialNumber' => $serial,
            'imageUrl' => 'https://example.com/saw.jpg',
            'dailyRentCost' => '5',
            'status' => 'rented',
            '_token' => $crawler->filter('input[name="tool[_token]"]')->attr('value'),
        ]]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertNull($this->findBySerial($serial));
    }

    public function testShowDisplaysTool(): void
    {
        $tool = $this->createTool();
        $this->client->request('GET', '/tools/'.$tool->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $tool->getName());
        $this->assertSelectorTextContains('main', 'Available');
    }

    public function testShowReturns404ForUnknownTool(): void
    {
        $this->client->request('GET', '/tools/999999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testEditUpdatesTool(): void
    {
        $tool = $this->createTool();

        $crawler = $this->client->request('GET', '/tools/'.$tool->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Save changes')->form([
            'tool[name]' => 'Renamed',
            'tool[status]' => 'in_repair',
            'tool[dailyRentCost]' => '30',
        ]));

        $this->assertResponseRedirects('/tools');
        $updated = $this->findBySerial($tool->getSerialNumber());
        $this->assertSame('Renamed', $updated->getName());
        $this->assertSame(ToolStatus::InRepair, $updated->getStatus());
        $this->assertSame('30.00', $updated->getDailyRentCost());
    }

    public function testEditRejectsInvalidDataAndKeepsTool(): void
    {
        $tool = $this->createTool();

        $crawler = $this->client->request('GET', '/tools/'.$tool->getId().'/edit');
        $this->client->submit($crawler->selectButton('Save changes')->form(['tool[name]' => '']));

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame($tool->getName(), $this->findBySerial($tool->getSerialNumber())->getName());
    }

    public function testRentedToolStatusCannotBeChangedManually(): void
    {
        $tool = $this->createTool(ToolStatus::Rented);

        $crawler = $this->client->request('GET', '/tools/'.$tool->getId().'/edit');
        $this->assertSelectorExists('select[name="tool[status]"][disabled]');
        $this->client->request('POST', '/tools/'.$tool->getId().'/edit', ['tool' => [
            'name' => $tool->getName(),
            'serialNumber' => $tool->getSerialNumber(),
            'imageUrl' => $tool->getImageUrl(),
            'dailyRentCost' => '12.50',
            'status' => 'available',
            '_token' => $crawler->filter('input[name="tool[_token]"]')->attr('value'),
        ]]);

        $this->assertSame(ToolStatus::Rented, $this->findBySerial($tool->getSerialNumber())->getStatus());
    }

    public function testDeleteRemovesToolWithValidToken(): void
    {
        $tool = $this->createTool();
        $id = $tool->getId();

        $crawler = $this->client->request('GET', '/tools/'.$id);
        $this->client->request('POST', '/tools/'.$id, [
            '_token' => $crawler->filter('dialog input[name="_token"]')->attr('value'),
        ]);

        $this->assertResponseRedirects('/tools');
        $this->client->followRedirect();
        $this->assertSelectorExists('[role="status"]');
        $this->assertNull(static::getContainer()->get(ToolRepository::class)->find($id));
    }

    public function testDeleteWithInvalidTokenKeepsTool(): void
    {
        $tool = $this->createTool();

        $this->client->request('POST', '/tools/'.$tool->getId(), ['_token' => 'invalid']);

        $this->assertResponseRedirects('/tools');
        $this->client->followRedirect();
        $this->assertSelectorExists('[role="alert"]');
        $this->assertNotNull($this->findBySerial($tool->getSerialNumber()));
    }

    public function testToolWithRentalHistoryCannotBeDeleted(): void
    {
        $tool = $this->createTool();
        $connection = static::getContainer()->get('doctrine')->getConnection();
        // Stand-in for RentalItem until that entity exists: a RESTRICT foreign key to tools.
        $connection->executeStatement('CREATE TABLE tool_history_stub (id SERIAL PRIMARY KEY, tool_id INT NOT NULL REFERENCES tools(id) ON DELETE RESTRICT)');

        try {
            $connection->executeStatement('INSERT INTO tool_history_stub (tool_id) VALUES (?)', [$tool->getId()]);

            $crawler = $this->client->request('GET', '/tools/'.$tool->getId());
            $this->client->request('POST', '/tools/'.$tool->getId(), [
                '_token' => $crawler->filter('dialog input[name="_token"]')->attr('value'),
            ]);

            $this->assertResponseRedirects('/tools');
            $this->client->followRedirect();
            $this->assertSelectorTextContains('[role="alert"]', 'This tool cannot be deleted because it has rental history.');
            $this->assertNotNull($this->findBySerial($tool->getSerialNumber()));
        } finally {
            $connection->executeStatement('DROP TABLE tool_history_stub');
        }
    }

    private function createTool(ToolStatus $status = ToolStatus::Available, ?string $name = null): Tool
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        $tool = (new Tool())
            ->setName($name ?? 'Drill '.uniqid())
            ->setSerialNumber('SN-'.uniqid())
            ->setImageUrl('https://example.com/tool.jpg')
            ->setDailyRentCost('12.50')
            ->setStatus($status);
        $em->persist($tool);
        $em->flush();

        return $tool;
    }

    private function findBySerial(string $serial): ?Tool
    {
        static::getContainer()->get('doctrine')->getManager()->clear();

        return static::getContainer()->get(ToolRepository::class)->findOneBy(['serialNumber' => $serial]);
    }
}

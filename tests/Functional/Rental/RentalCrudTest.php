<?php

namespace App\Tests\Functional\Rental;

use App\Entity\Customer;
use App\Entity\Rental;
use App\Entity\Tool;
use App\Entity\ToolStatus;
use App\Entity\User;
use App\Repository\RentalRepository;
use App\Repository\ToolRepository;
use App\Service\RentalManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RentalCrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->user = (new User())->setEmail('rentals'.uniqid().'@example.com')->setPassword('not-used');
        $this->em()->persist($this->user);
        $this->em()->flush();
        $this->client->loginUser($this->user);
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/rentals');

        $this->assertResponseRedirects('/login');
    }

    public function testIndexListsRentalsAndHasNavLink(): void
    {
        $rental = $this->createRental();

        $this->client->request('GET', '/rentals', ['q' => $rental->getCustomer()->getEmail()]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Rentals');
        $this->assertSelectorTextContains('table', $rental->getCustomer()->getName());
        $this->assertSelectorTextContains('table', $rental->getItems()->first()->getTool()->getName());
        $this->assertSelectorExists('nav a[href="/rentals"]');
    }

    public function testIndexSearchesByCustomerAndTool(): void
    {
        $rental = $this->createRental();
        $other = $this->createRental();

        $this->client->request('GET', '/rentals', ['q' => strtoupper($rental->getCustomer()->getName())]);
        $this->assertSelectorTextContains('table', $rental->getCustomer()->getName());
        $this->assertSelectorTextNotContains('table', $other->getCustomer()->getName());

        $this->client->request('GET', '/rentals', ['q' => $other->getItems()->first()->getTool()->getSerialNumber()]);
        $this->assertSelectorTextContains('table', $other->getCustomer()->getName());
        $this->assertSelectorTextNotContains('table', $rental->getCustomer()->getName());
    }

    public function testIndexFiltersByStatus(): void
    {
        $active = $this->createRental();
        $cancelled = $this->createRental();
        static::getContainer()->get(RentalManager::class)->cancel($cancelled);

        $this->client->request('GET', '/rentals', ['status' => 'cancelled', 'q' => 'Customer']);
        $this->assertSelectorTextContains('table', $cancelled->getCustomer()->getName());
        $this->assertSelectorTextNotContains('table', $active->getCustomer()->getName());

        $this->client->request('GET', '/rentals', ['status' => 'active', 'q' => 'Customer']);
        $this->assertSelectorTextContains('table', $active->getCustomer()->getName());
        $this->assertSelectorTextNotContains('table', $cancelled->getCustomer()->getName());
    }

    public function testIndexShowsEmptyStateAndIgnoresInvalidParameters(): void
    {
        $this->client->request('GET', '/rentals', ['q' => 'no-such-rental-'.uniqid(), 'sort' => 'x; DROP', 'dir' => 'up', 'status' => 'bogus']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'No rentals match your search.');
    }

    public function testNewFormOffersOnlyAvailableTools(): void
    {
        $available = $this->createTool();
        $inRepair = $this->createTool(ToolStatus::InRepair);
        $rented = $this->createTool(ToolStatus::Rented);
        $retired = $this->createTool(ToolStatus::Retired);

        $this->client->request('GET', '/rentals/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(sprintf('input[type="checkbox"][value="%d"]', $available->getId()));
        foreach ([$inRepair, $rented, $retired] as $tool) {
            $this->assertSelectorNotExists(sprintf('input[type="checkbox"][value="%d"]', $tool->getId()));
        }
    }

    public function testCreateRentalMarksToolsRentedAndCopiesPrices(): void
    {
        $customer = $this->createCustomer();
        $first = $this->createTool(cost: '10.00');
        $second = $this->createTool(cost: '5.50');

        $this->submitNewRental($customer, [$first, $second], '2026-03-01T10:00', '2026-03-03T10:00');

        $this->assertResponseRedirects();
        $rental = $this->findLatestRentalOf($customer);
        $this->assertSame($this->user->getId(), $rental->getUser()->getId());
        $this->assertCount(2, $rental->getItems());
        // 2 days x (10.00 + 5.50)
        $this->assertSame('31.00', $rental->getTotalRentCost());
        $this->assertTrue($rental->isActive());
        foreach ([$first, $second] as $tool) {
            $this->assertSame(ToolStatus::Rented, $this->reloadTool($tool)->getStatus());
        }
        $this->assertSame('10.00', $rental->getItems()->first()->getDailyRentCost());

        // Later price changes must not alter the rental history.
        $reloaded = $this->reloadTool($first)->setDailyRentCost('99.00');
        $this->em()->flush();
        $this->assertSame('10.00', $this->findLatestRentalOf($customer)->getItems()->first()->getDailyRentCost());
        $this->assertSame('99.00', $reloaded->getDailyRentCost());
    }

    public function testCreateRentalRequiresAtLeastOneTool(): void
    {
        $customer = $this->createCustomer();

        $this->submitNewRental($customer, [], '2026-03-01T10:00', '2026-03-02T10:00');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.form-error', 'Select at least one tool.');
        $this->assertNull($this->findLatestRentalOf($customer));
    }

    public function testCreateRentalRequiresExpectedReturnAfterStart(): void
    {
        $customer = $this->createCustomer();
        $tool = $this->createTool();

        $this->submitNewRental($customer, [$tool], '2026-03-01T10:00', '2026-03-01T10:00');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.form-error', 'The expected return date must be later than the start date.');
        $this->assertSame(ToolStatus::Available, $this->reloadTool($tool)->getStatus());
    }

    public function testCreateRentalRequiresCustomer(): void
    {
        $tool = $this->createTool();

        $this->submitNewRental(null, [$tool], '2026-03-01T10:00', '2026-03-02T10:00');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(ToolStatus::Available, $this->reloadTool($tool)->getStatus());
    }

    public function testCreateRentalRejectsToolsThatAreNotAvailable(): void
    {
        $customer = $this->createCustomer();
        foreach ([ToolStatus::Rented, ToolStatus::InRepair, ToolStatus::Retired] as $status) {
            $tool = $this->createTool($status);

            $this->submitNewRental($customer, [$tool], '2026-03-01T10:00', '2026-03-02T10:00');

            $this->assertResponseStatusCodeSame(422);
            $this->assertSame($status, $this->reloadTool($tool)->getStatus());
        }
        $this->assertNull($this->findLatestRentalOf($customer));
    }

    public function testToolCannotBeRentedTwice(): void
    {
        $tool = $this->createTool();
        $this->createRental([$tool]);

        $this->expectException(\App\Exception\RentalException::class);
        $rental = (new Rental())
            ->setUser($this->user)
            ->setCustomer($this->createCustomer())
            ->setRentalStartDate(new \DateTimeImmutable('2026-03-01 10:00'))
            ->setExpectedReturnDate(new \DateTimeImmutable('2026-03-02 10:00'));
        static::getContainer()->get(RentalManager::class)->create($rental, [$tool]);
    }

    public function testShowDisplaysRentalWithItems(): void
    {
        $rental = $this->createRental();
        $this->client->request('GET', '/rentals/'.$rental->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Rental #'.$rental->getId());
        $this->assertSelectorTextContains('main', $rental->getItems()->first()->getTool()->getName());
        $this->assertSelectorTextContains('main', 'Active');
        $this->assertSelectorExists('a[href$="/return"]');
    }

    public function testShowReturns404ForUnknownRental(): void
    {
        $this->client->request('GET', '/rentals/999999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testRentalsAndItemsCannotBeDeleted(): void
    {
        $rental = $this->createRental();

        $this->client->request('POST', '/rentals/'.$rental->getId());
        $this->assertResponseStatusCodeSame(405);
        $this->client->request('DELETE', '/rentals/'.$rental->getId());
        $this->assertResponseStatusCodeSame(405);
        $this->assertNotNull(static::getContainer()->get(RentalRepository::class)->find($rental->getId()));
    }

    public function testEditUpdatesDatesAndEstimatedCost(): void
    {
        $rental = $this->createRental(cost: '10.00');
        $crawler = $this->client->request('GET', '/rentals/'.$rental->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('input[type="checkbox"]');

        $start = $rental->getRentalStartDate();
        $this->client->submit($crawler->selectButton('Save changes')->form([
            'rental[rentalStartDate]' => $start->format('Y-m-d\TH:i'),
            'rental[expectedReturnDate]' => $start->modify('+3 days')->format('Y-m-d\TH:i'),
        ]));

        $this->assertResponseRedirects('/rentals/'.$rental->getId());
        $this->em()->clear();
        $this->assertSame('30.00', static::getContainer()->get(RentalRepository::class)->find($rental->getId())->getTotalRentCost());
    }

    public function testEditRejectsInvalidDates(): void
    {
        $rental = $this->createRental();
        $crawler = $this->client->request('GET', '/rentals/'.$rental->getId().'/edit');

        $start = $rental->getRentalStartDate();
        $this->client->submit($crawler->selectButton('Save changes')->form([
            'rental[rentalStartDate]' => $start->format('Y-m-d\TH:i'),
            'rental[expectedReturnDate]' => $start->modify('-1 day')->format('Y-m-d\TH:i'),
        ]));

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCancelledRentalCannotBeEdited(): void
    {
        $rental = $this->createRental();
        static::getContainer()->get(RentalManager::class)->cancel($rental);

        $this->client->request('GET', '/rentals/'.$rental->getId().'/edit');

        $this->assertResponseRedirects('/rentals/'.$rental->getId());
    }

    public function testReturnSetsToolStatusesAndFinalCost(): void
    {
        $fine = $this->createTool(cost: '10.00');
        $broken = $this->createTool(cost: '5.00');
        $rental = $this->createRental([$fine, $broken], start: new \DateTimeImmutable('-50 hours'), end: new \DateTimeImmutable('+1 day'));

        $crawler = $this->client->request('GET', '/rentals/'.$rental->getId().'/return');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Complete return')->form();
        $form['rental_return[repairTools]'][1]->tick();
        $this->client->submit($form);

        $this->assertResponseRedirects('/rentals/'.$rental->getId());
        $this->em()->clear();
        $returned = static::getContainer()->get(RentalRepository::class)->find($rental->getId());
        $this->assertNotNull($returned->getActualReturnDate());
        $this->assertFalse($returned->isActive());
        // 50 hours = 3 started days x 15.00
        $this->assertSame('45.00', $returned->getTotalRentCost());
        $this->assertSame(ToolStatus::Available, $this->reloadTool($fine)->getStatus());
        $this->assertSame(ToolStatus::InRepair, $this->reloadTool($broken)->getStatus());
    }

    public function testReturnedRentalCannotBeReturnedAgainOrCancelled(): void
    {
        $rental = $this->createRental(start: new \DateTimeImmutable('-2 hours'), end: new \DateTimeImmutable('+1 day'));
        static::getContainer()->get(RentalManager::class)->return($rental, []);

        $this->client->request('GET', '/rentals/'.$rental->getId().'/return');
        $this->assertResponseRedirects('/rentals/'.$rental->getId());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[role="alert"]', 'Only active rentals can be returned.');
        $this->assertSelectorNotExists('a[href$="/return"]');
    }

    public function testRentalThatHasNotStartedCannotBeReturned(): void
    {
        $tool = $this->createTool();
        $rental = $this->createRental([$tool], start: new \DateTimeImmutable('+2 days'), end: new \DateTimeImmutable('+4 days'));

        $crawler = $this->client->request('GET', '/rentals/'.$rental->getId().'/return');
        $this->client->submit($crawler->selectButton('Complete return')->form());

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.form-error', 'has not started yet');
        $this->assertSame(ToolStatus::Rented, $this->reloadTool($tool)->getStatus());
    }

    public function testCancelKeepsRentalAndFreesTools(): void
    {
        $tool = $this->createTool();
        $rental = $this->createRental([$tool]);

        $crawler = $this->client->request('GET', '/rentals/'.$rental->getId());
        $this->client->request('POST', '/rentals/'.$rental->getId().'/cancel', [
            '_token' => $crawler->filter('dialog input[name="_token"]')->attr('value'),
        ]);

        $this->assertResponseRedirects('/rentals/'.$rental->getId());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[role="status"]', 'was cancelled');
        $this->em()->clear();
        $kept = static::getContainer()->get(RentalRepository::class)->find($rental->getId());
        $this->assertTrue($kept->isCancelled());
        $this->assertCount(1, $kept->getItems());
        $this->assertSame(ToolStatus::Available, $this->reloadTool($tool)->getStatus());
    }

    public function testCancelWithInvalidTokenChangesNothing(): void
    {
        $tool = $this->createTool();
        $rental = $this->createRental([$tool]);

        $this->client->request('POST', '/rentals/'.$rental->getId().'/cancel', ['_token' => 'invalid']);

        $this->assertResponseRedirects('/rentals/'.$rental->getId());
        $this->em()->clear();
        $this->assertFalse(static::getContainer()->get(RentalRepository::class)->find($rental->getId())->isCancelled());
        $this->assertSame(ToolStatus::Rented, $this->reloadTool($tool)->getStatus());
    }

    public function testToolWithRentalHistoryCannotBeDeleted(): void
    {
        $rental = $this->createRental();
        $tool = $rental->getItems()->first()->getTool();

        $crawler = $this->client->request('GET', '/tools/'.$tool->getId());
        $this->client->request('POST', '/tools/'.$tool->getId(), [
            '_token' => $crawler->filter('dialog input[name="_token"]')->attr('value'),
        ]);

        $this->client->followRedirect();
        $this->assertSelectorTextContains('[role="alert"]', 'This tool cannot be deleted because it has rental history.');
    }

    public function testCustomerWithRentalsCannotBeDeletedAndShowsHistory(): void
    {
        $rental = $this->createRental();
        $customer = $rental->getCustomer();

        $crawler = $this->client->request('GET', '/customers/'.$customer->getId());
        $this->assertSelectorTextContains('#rental-history + div', '#'.$rental->getId());
        $this->client->request('POST', '/customers/'.$customer->getId(), [
            '_token' => $crawler->filter('dialog input[name="_token"]')->attr('value'),
        ]);

        $this->client->followRedirect();
        $this->assertSelectorTextContains('[role="alert"]', 'This customer cannot be deleted because they have rental history.');
    }

    /**
     * @param list<Tool> $tools
     */
    private function submitNewRental(?Customer $customer, array $tools, string $start, string $end): void
    {
        $crawler = $this->client->request('GET', '/rentals/new');
        $this->client->request('POST', '/rentals/new', ['rental' => [
            'customer' => $customer?->getId() ?? '',
            'rentalStartDate' => $start,
            'expectedReturnDate' => $end,
            'tools' => array_map(static fn (Tool $tool): ?int => $tool->getId(), $tools),
            '_token' => $crawler->filter('input[name="rental[_token]"]')->attr('value'),
        ]]);
    }

    /**
     * @param list<Tool>|null $tools
     */
    private function createRental(?array $tools = null, string $cost = '12.50', ?\DateTimeImmutable $start = null, ?\DateTimeImmutable $end = null): Rental
    {
        $rental = (new Rental())
            ->setUser($this->user)
            ->setCustomer($this->createCustomer())
            ->setRentalStartDate($start ?? new \DateTimeImmutable('-1 hour'))
            ->setExpectedReturnDate($end ?? new \DateTimeImmutable('+1 day'));

        static::getContainer()->get(RentalManager::class)->create($rental, $tools ?? [$this->createTool(cost: $cost)]);

        return $rental;
    }

    private function createTool(ToolStatus $status = ToolStatus::Available, string $cost = '12.50'): Tool
    {
        $tool = (new Tool())
            ->setName('Drill '.uniqid())
            ->setSerialNumber('SN-'.uniqid())
            ->setImageUrl('https://example.com/tool.jpg')
            ->setDailyRentCost($cost)
            ->setStatus($status);
        $this->em()->persist($tool);
        $this->em()->flush();

        return $tool;
    }

    private function createCustomer(): Customer
    {
        $unique = uniqid();
        $customer = (new Customer())->setName('Customer '.$unique)->setEmail('r'.$unique.'@example.com');
        $this->em()->persist($customer);
        $this->em()->flush();

        return $customer;
    }

    private function findLatestRentalOf(Customer $customer): ?Rental
    {
        $this->em()->clear();

        return static::getContainer()->get(RentalRepository::class)->findOneBy(['customer' => $customer->getId()]);
    }

    private function reloadTool(Tool $tool): Tool
    {
        $this->em()->clear();

        return static::getContainer()->get(ToolRepository::class)->find($tool->getId());
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }
}

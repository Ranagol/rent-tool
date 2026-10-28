<?php

namespace App\Tests\Functional\Customer;

use App\Entity\Customer;
use App\Entity\User;
use App\Repository\CustomerRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CustomerCrudTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $user = (new User())->setEmail('cust-admin'.uniqid().'@example.com')->setPassword('not-used');
        $em->persist($user);
        $em->flush();
        $this->client->loginUser($user);
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/customers');

        $this->assertResponseRedirects('/login');
    }

    public function testIndexListsCustomersAndHasNavLink(): void
    {
        $customer = $this->createCustomer();

        $this->client->request('GET', '/customers', ['q' => $customer->getEmail()]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Customers');
        $this->assertSelectorTextContains('table', $customer->getName());
        $this->assertSelectorExists('nav a[href="/customers"]');
    }

    public function testIndexSearchesByNameEmailAndPhone(): void
    {
        $customer = $this->createCustomer();
        $other = $this->createCustomer();

        $this->client->request('GET', '/customers', ['q' => strtoupper($customer->getName())]);
        $this->assertSelectorTextContains('table', $customer->getName());
        $this->assertSelectorTextNotContains('table', $other->getName());

        $this->client->request('GET', '/customers', ['q' => $other->getEmail()]);
        $this->assertSelectorTextContains('table', $other->getName());
        $this->assertSelectorTextNotContains('table', $customer->getName());

        $this->client->request('GET', '/customers', ['q' => $customer->getPhone()]);
        $this->assertSelectorTextContains('table', $customer->getName());
        $this->assertSelectorTextNotContains('table', $other->getName());
    }

    public function testIndexShowsEmptyStateWhenNothingMatches(): void
    {
        $this->client->request('GET', '/customers', ['q' => 'no-such-customer-'.uniqid()]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'No customers match your search.');
        $this->assertSelectorNotExists('table');
    }

    public function testIndexIgnoresInvalidSortAndDirection(): void
    {
        $this->client->request('GET', '/customers', ['sort' => 'password; DROP TABLE customers', 'dir' => 'sideways']);

        $this->assertResponseIsSuccessful();
    }

    public function testIndexPaginates(): void
    {
        $marker = 'Paged'.uniqid();
        for ($i = 0; $i < 11; ++$i) {
            $this->createCustomer($marker.$i);
        }

        $this->client->request('GET', '/customers', ['q' => $marker]);

        $this->assertSelectorExists('nav[aria-label="Pagination"] a[rel="next"]');
    }

    public function testCreateCustomerWithValidData(): void
    {
        $email = 'new'.uniqid().'@example.com';
        $crawler = $this->client->request('GET', '/customers/new');
        $this->assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Create customer')->form([
            'customer[name]' => 'Jane Builder',
            'customer[email]' => $email,
            'customer[city]' => 'Vienna',
        ]));

        $this->assertResponseRedirects('/customers');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[role="status"]', 'Jane Builder');

        $customer = $this->findByEmail($email);
        $this->assertNotNull($customer);
        $this->assertSame('Vienna', $customer->getCity());
        $this->assertNull($customer->getPhone());
    }

    public function testCreateCustomerRejectsMissingRequiredFields(): void
    {
        $crawler = $this->client->request('GET', '/customers/new');
        $this->client->submit($crawler->selectButton('Create customer')->form());

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('.form-error[role="alert"]');
    }

    public function testCreateCustomerRejectsInvalidEmail(): void
    {
        $crawler = $this->client->request('GET', '/customers/new');
        $form = $crawler->selectButton('Create customer')->form(['customer[name]' => 'Bad Mail']);
        $form->setValues(['customer[email]' => 'not-an-email']);
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertNull($this->findByEmail('not-an-email'));
    }

    public function testCreateCustomerRejectsTooLongPhone(): void
    {
        $email = 'long'.uniqid().'@example.com';
        $crawler = $this->client->request('GET', '/customers/new');
        $this->client->submit($crawler->selectButton('Create customer')->form([
            'customer[name]' => 'Long Phone',
            'customer[email]' => $email,
            'customer[phone]' => str_repeat('1', 31),
        ]));

        $this->assertResponseStatusCodeSame(422);
        $this->assertNull($this->findByEmail($email));
    }

    public function testCreateCustomerRejectsDuplicateEmail(): void
    {
        $existing = $this->createCustomer();
        $crawler = $this->client->request('GET', '/customers/new');
        $this->client->submit($crawler->selectButton('Create customer')->form([
            'customer[name]' => 'Another',
            'customer[email]' => $existing->getEmail(),
        ]));

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.form-error', 'Email is already in use');
    }

    public function testShowDisplaysCustomer(): void
    {
        $customer = $this->createCustomer();
        $this->client->request('GET', '/customers/'.$customer->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $customer->getName());
        $this->assertSelectorTextContains('main', $customer->getEmail());
        $this->assertSelectorTextContains('main', 'Rental history');
    }

    public function testShowReturns404ForUnknownCustomer(): void
    {
        $this->client->request('GET', '/customers/999999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testEditUpdatesCustomer(): void
    {
        $customer = $this->createCustomer();

        $crawler = $this->client->request('GET', '/customers/'.$customer->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Save changes')->form([
            'customer[name]' => 'Renamed',
            'customer[city]' => 'Graz',
        ]));

        $this->assertResponseRedirects('/customers');
        $updated = $this->findByEmail($customer->getEmail());
        $this->assertSame('Renamed', $updated->getName());
        $this->assertSame('Graz', $updated->getCity());
    }

    public function testEditRejectsInvalidDataAndKeepsCustomer(): void
    {
        $customer = $this->createCustomer();

        $crawler = $this->client->request('GET', '/customers/'.$customer->getId().'/edit');
        $this->client->submit($crawler->selectButton('Save changes')->form(['customer[name]' => '']));

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame($customer->getName(), $this->findByEmail($customer->getEmail())->getName());
    }

    public function testEditCanKeepOwnEmail(): void
    {
        $customer = $this->createCustomer();

        $crawler = $this->client->request('GET', '/customers/'.$customer->getId().'/edit');
        $this->client->submit($crawler->selectButton('Save changes')->form());

        $this->assertResponseRedirects('/customers');
    }

    public function testDeleteRemovesCustomerWithValidToken(): void
    {
        $customer = $this->createCustomer();
        $id = $customer->getId();

        $crawler = $this->client->request('GET', '/customers/'.$id);
        $this->client->request('POST', '/customers/'.$id, [
            '_token' => $crawler->filter('dialog input[name="_token"]')->attr('value'),
        ]);

        $this->assertResponseRedirects('/customers');
        $this->client->followRedirect();
        $this->assertSelectorExists('[role="status"]');
        $this->assertNull(static::getContainer()->get(CustomerRepository::class)->find($id));
    }

    public function testDeleteWithInvalidTokenKeepsCustomer(): void
    {
        $customer = $this->createCustomer();

        $this->client->request('POST', '/customers/'.$customer->getId(), ['_token' => 'invalid']);

        $this->assertResponseRedirects('/customers');
        $this->client->followRedirect();
        $this->assertSelectorExists('[role="alert"]');
        $this->assertNotNull($this->findByEmail($customer->getEmail()));
    }

    public function testCustomerWithRentalHistoryCannotBeDeleted(): void
    {
        $customer = $this->createCustomer();
        $connection = static::getContainer()->get('doctrine')->getConnection();
        // Stand-in for Rental until that entity exists: a RESTRICT foreign key to customers.
        $connection->executeStatement('CREATE TABLE customer_history_stub (id SERIAL PRIMARY KEY, customer_id INT NOT NULL REFERENCES customers(id) ON DELETE RESTRICT)');

        try {
            $connection->executeStatement('INSERT INTO customer_history_stub (customer_id) VALUES (?)', [$customer->getId()]);

            $crawler = $this->client->request('GET', '/customers/'.$customer->getId());
            $this->client->request('POST', '/customers/'.$customer->getId(), [
                '_token' => $crawler->filter('dialog input[name="_token"]')->attr('value'),
            ]);

            $this->assertResponseRedirects('/customers');
            $this->client->followRedirect();
            $this->assertSelectorTextContains('[role="alert"]', 'This customer cannot be deleted because they have rental history.');
            $this->assertNotNull($this->findByEmail($customer->getEmail()));
        } finally {
            $connection->executeStatement('DROP TABLE customer_history_stub');
        }
    }

    private function createCustomer(?string $name = null): Customer
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $unique = uniqid();

        $customer = (new Customer())
            ->setName($name ?? 'Customer '.$unique)
            ->setEmail('c'.$unique.'@example.com')
            ->setPhone('+43'.random_int(100000000, 999999999));
        $em->persist($customer);
        $em->flush();

        return $customer;
    }

    private function findByEmail(string $email): ?Customer
    {
        static::getContainer()->get('doctrine')->getManager()->clear();

        return static::getContainer()->get(CustomerRepository::class)->findOneBy(['email' => $email]);
    }
}

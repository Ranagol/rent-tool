<?php

namespace App\Tests\Functional\User;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $admin;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->admin = $this->createUser('admin'.uniqid().'@example.com');
        $this->client->loginUser($this->admin);
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/users');

        $this->assertResponseRedirects('/login');
    }

    public function testIndexListsUsersAndHasNavLink(): void
    {
        $this->client->request('GET', '/users', ['q' => $this->admin->getEmail()]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Users');
        $this->assertSelectorTextContains('table', $this->admin->getEmail());
        $this->assertSelectorExists('nav a[href="/users"]');
    }

    public function testIndexSearchFiltersByEmail(): void
    {
        $needle = 'needle'.uniqid();
        $this->createUser($needle.'@example.com');

        $this->client->request('GET', '/users', ['q' => $needle]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('table', $needle);
        $this->assertSelectorTextNotContains('table', $this->admin->getEmail());
    }

    public function testIndexShowsEmptyStateWhenNothingMatches(): void
    {
        $this->client->request('GET', '/users', ['q' => 'no-such-user-'.uniqid()]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'No users match your search.');
        $this->assertSelectorNotExists('table');
    }

    public function testIndexIgnoresInvalidSortField(): void
    {
        $this->client->request('GET', '/users', ['sort' => 'password; DROP TABLE user', 'dir' => 'sideways']);

        $this->assertResponseIsSuccessful();
    }

    public function testIndexPaginates(): void
    {
        for ($i = 0; $i < 11; ++$i) {
            $this->createUser('page'.$i.'-'.uniqid().'@example.com');
        }

        $this->client->request('GET', '/users');

        $this->assertSelectorExists('nav[aria-label="Pagination"] a[rel="next"]');
    }

    public function testCreateUserWithValidData(): void
    {
        $email = 'new'.uniqid().'@example.com';
        $crawler = $this->client->request('GET', '/users/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create user')->form([
            'user[email]' => $email,
            'user[plainPassword][first]' => 'Password123',
            'user[plainPassword][second]' => 'Password123',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/users');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[role="status"]', $email);

        $created = $this->findUser($email);
        $this->assertNotNull($created);
        $this->assertNotSame('Password123', $created->getPassword());
        $this->assertTrue(
            static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($created, 'Password123')
        );
    }

    public function testCreateUserRejectsInvalidData(): void
    {
        $crawler = $this->client->request('GET', '/users/new');
        $form = $crawler->selectButton('Create user')->form([
            'user[email]' => 'not-an-email',
            'user[plainPassword][first]' => 'short',
            'user[plainPassword][second]' => 'different',
        ]);
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('.form-error[role="alert"]');
        $this->assertNull($this->findUser('not-an-email'));
    }

    public function testCreateUserRequiresPassword(): void
    {
        $email = 'nopass'.uniqid().'@example.com';
        $crawler = $this->client->request('GET', '/users/new');
        $form = $crawler->selectButton('Create user')->form(['user[email]' => $email]);
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertNull($this->findUser($email));
    }

    public function testCreateUserRejectsDuplicateEmail(): void
    {
        $crawler = $this->client->request('GET', '/users/new');
        $form = $crawler->selectButton('Create user')->form([
            'user[email]' => $this->admin->getEmail(),
            'user[plainPassword][first]' => 'Password123',
            'user[plainPassword][second]' => 'Password123',
        ]);
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.form-error', 'Email is already in use');
    }

    public function testShowDisplaysUser(): void
    {
        $this->client->request('GET', '/users/'.$this->admin->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', $this->admin->getEmail());
    }

    public function testShowReturns404ForUnknownUser(): void
    {
        $this->client->request('GET', '/users/999999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testEditChangesEmailAndKeepsPasswordWhenEmpty(): void
    {
        $user = $this->createUser('edit'.uniqid().'@example.com');
        $originalHash = $user->getPassword();
        $newEmail = 'edited'.uniqid().'@example.com';

        $crawler = $this->client->request('GET', '/users/'.$user->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Save changes')->form(['user[email]' => $newEmail]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/users');
        $updated = $this->findUser($newEmail);
        $this->assertNotNull($updated);
        $this->assertSame($originalHash, $updated->getPassword());
    }

    public function testEditChangesPassword(): void
    {
        $user = $this->createUser('pw'.uniqid().'@example.com');
        $originalHash = $user->getPassword();

        $crawler = $this->client->request('GET', '/users/'.$user->getId().'/edit');
        $form = $crawler->selectButton('Save changes')->form([
            'user[plainPassword][first]' => 'BrandNewPass123',
            'user[plainPassword][second]' => 'BrandNewPass123',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/users');
        $updated = $this->findUser($user->getEmail());
        $this->assertNotSame($originalHash, $updated->getPassword());
    }

    public function testEditRejectsInvalidEmail(): void
    {
        $user = $this->createUser('invalid'.uniqid().'@example.com');

        $crawler = $this->client->request('GET', '/users/'.$user->getId().'/edit');
        $form = $crawler->selectButton('Save changes')->form(['user[email]' => 'nope']);
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('.form-error[role="alert"]');
        $this->assertNotNull($this->findUser($user->getEmail()));
    }

    public function testDeleteRemovesUserWithValidToken(): void
    {
        $user = $this->createUser('delete'.uniqid().'@example.com');
        $id = $user->getId();

        $crawler = $this->client->request('GET', '/users/'.$id);
        $this->client->request('POST', '/users/'.$id, [
            '_token' => $crawler->filter('dialog input[name="_token"]')->attr('value'),
        ]);

        $this->assertResponseRedirects('/users');
        $this->client->followRedirect();
        $this->assertSelectorExists('[role="status"]');
        $this->assertNull(static::getContainer()->get(UserRepository::class)->find($id));
    }

    public function testDeleteWithInvalidTokenKeepsUser(): void
    {
        $user = $this->createUser('keep'.uniqid().'@example.com');

        $this->client->request('POST', '/users/'.$user->getId(), ['_token' => 'invalid']);

        $this->assertResponseRedirects('/users');
        $this->client->followRedirect();
        $this->assertSelectorExists('[role="alert"]');
        $this->assertNotNull($this->findUser($user->getEmail()));
    }

    public function testUserCannotDeleteOwnAccount(): void
    {
        $crawler = $this->client->request('GET', '/users/'.$this->admin->getId());
        $this->client->request('POST', '/users/'.$this->admin->getId(), [
            '_token' => $crawler->filter('dialog input[name="_token"]')->attr('value'),
        ]);

        $this->assertResponseRedirects('/users');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[role="alert"]', 'cannot delete your own account');
        $this->assertNotNull($this->findUser($this->admin->getEmail()));
    }

    public function testDeleteRejectsGetRequest(): void
    {
        $user = $this->createUser('getdel'.uniqid().'@example.com');
        $this->client->request('GET', '/users/'.$user->getId().'/delete');

        $this->assertResponseStatusCodeSame(404);
        $this->assertNotNull($this->findUser($user->getEmail()));
    }

    private function createUser(string $email): User
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, 'TestPassword123'));
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function findUser(string $email): ?User
    {
        static::getContainer()->get('doctrine')->getManager()->clear();

        return static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
    }
}

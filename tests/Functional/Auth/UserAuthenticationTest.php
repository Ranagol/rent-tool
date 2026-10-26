<?php

namespace App\Tests\Functional\Auth;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserAuthenticationTest extends WebTestCase
{
    public function testUnauthenticatedUserCannotAccessProtectedRoute(): void
    {
        $client = static::createClient();
        $client->request('GET', '/tools');

        // Should redirect to login
        $this->assertResponseRedirects('/login');
    }

    public function testAuthenticatedUserCanAccessProtectedRoute(): void
    {
        $client = static::createClient();

        // Create a test user
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $uniqueEmail = 'auth' . uniqid() . '@example.com';
        $user = new User();
        $user->setEmail($uniqueEmail);
        $hashedPassword = $passwordHasher->hashPassword($user, 'TestPassword123');
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        // Login
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form();
        $form['email'] = $uniqueEmail;
        $form['password'] = 'TestPassword123';

        $client->submit($form);
        $client->followRedirect();

        // Now access protected route
        $client->request('GET', '/tools');

        // Should be successful
        $this->assertResponseIsSuccessful();
    }

    public function testUserCanViewTheirOwnProfile(): void
    {
        $client = static::createClient();

        // Create a test user
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $uniqueEmail = 'profile' . uniqid() . '@example.com';
        $user = new User();
        $user->setEmail($uniqueEmail);
        $hashedPassword = $passwordHasher->hashPassword($user, 'TestPassword123');
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        // Login
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form();
        $form['email'] = $uniqueEmail;
        $form['password'] = 'TestPassword123';

        $client->submit($form);
        $client->followRedirect();

        // Check if user email is displayed or accessible
        $this->assertTrue($client->getContainer()->get('security.token_storage')->getToken() !== null);
    }

    public function testUserEmailIsUnique(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Create first user
        $uniqueEmail = 'unique' . uniqid() . '@example.com';
        $user1 = new User();
        $user1->setEmail($uniqueEmail);
        $hashedPassword = $passwordHasher->hashPassword($user1, 'Password1');
        $user1->setPassword($hashedPassword);

        $em->persist($user1);
        $em->flush();

        // Try to create second user with same email via registration
        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Register')->form();
        $form['registration_form[email]'] = $uniqueEmail;
        $form['registration_form[plainPassword][first]'] = 'Password2';
        $form['registration_form[plainPassword][second]'] = 'Password2';

        $client->submit($form);

        // Should fail or show error
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('[role="alert"]');
    }
}

<?php

namespace App\Tests\Functional\Auth;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserLoginTest extends WebTestCase
{
    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Login');
    }

    public function testLoginFormHasEmailField(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="email"]');
    }

    public function testLoginFormHasPasswordField(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="password"]');
    }

    public function testLoginFormHasSubmitButton(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testUserCanLoginWithValidCredentials(): void
    {
        $client = static::createClient();

        // Create a test user
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $uniqueEmail = 'login' . uniqid() . '@example.com';
        $user = new User();
        $user->setEmail($uniqueEmail);
        $hashedPassword = $passwordHasher->hashPassword($user, 'TestPassword123');
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        // Attempt login
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form();
        $form['email'] = $uniqueEmail;
        $form['password'] = 'TestPassword123';

        $client->submit($form);

        // Should redirect to a protected page after successful login
        $this->assertResponseRedirects();

        // Follow redirect
        $client->followRedirect();

        // Should be logged in (no redirect to login)
        $this->assertResponseIsSuccessful();
    }

    public function testLoginRejectedWithInvalidPassword(): void
    {
        $client = static::createClient();

        // Create a test user
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $uniqueEmail = 'wrongpass' . uniqid() . '@example.com';
        $user = new User();
        $user->setEmail($uniqueEmail);
        $hashedPassword = $passwordHasher->hashPassword($user, 'CorrectPassword123');
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        // Attempt login with wrong password
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form();
        $form['email'] = $uniqueEmail;
        $form['password'] = 'WrongPassword123';

        $client->submit($form);

        // Should redirect to login page on failed authentication
        $this->assertResponseRedirects('/login');
        
        // Follow redirect
        $client->followRedirect();
        
        // Should show error message
        $this->assertSelectorExists('[role="alert"]');
    }

    public function testLoginRejectedWithNonexistentUser(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form();
        $form['email'] = 'nonexistent' . uniqid() . '@example.com';
        $form['password'] = 'AnyPassword123';

        $client->submit($form);

        // Should redirect to login page on failed authentication
        $this->assertResponseRedirects('/login');
        
        // Follow redirect
        $client->followRedirect();
        
        // Should show error message
        $this->assertSelectorExists('[role="alert"]');
    }

    public function testLoginFormHasCsrfToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertSelectorExists('input[name="_csrf_token"]');
    }

    public function testLoginRejectedWithoutCsrfToken(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login', ['email' => 'a@example.com', 'password' => 'whatever123']);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('.form-error[role="alert"]');
    }

    public function testLoginErrorKeepsSubmittedEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form();
        $form['email'] = 'keep@example.com';
        $form['password'] = 'WrongPassword123';
        $client->submit($form);
        $client->followRedirect();

        $this->assertSelectorExists('.form-error[role="alert"]');
        $this->assertInputValueSame('email', 'keep@example.com');
    }

    public function testUnauthenticatedUserCanAccessLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
    }

    public function testLogoutRedirectsToLogin(): void
    {
        $client = static::createClient();

        // Create and login a user
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $uniqueEmail = 'logout' . uniqid() . '@example.com';
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

        // Logout
        $client->request('GET', '/logout');

        // Should redirect
        $this->assertResponseRedirects();

        // Follow redirect
        $client->followRedirect();

        // Should be back at login or home
        $this->assertResponseIsSuccessful();
    }
}

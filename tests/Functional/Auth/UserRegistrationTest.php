<?php

namespace App\Tests\Functional\Auth;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class UserRegistrationTest extends WebTestCase
{
    public function testRegistrationPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Register');
    }

    public function testRegistrationFormHasEmailField(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="registration_form[email]"]');
    }

    public function testRegistrationFormHasPasswordField(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="registration_form[plainPassword][first]"]');
    }

    public function testRegistrationFormHasSubmitButton(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testUserCanRegisterWithValidData(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Register')->form();
        $form['registration_form[email]'] = 'valid' . uniqid() . '@example.com';
        $form['registration_form[plainPassword][first]'] = 'SecurePassword123';
        $form['registration_form[plainPassword][second]'] = 'SecurePassword123';

        $client->submit($form);

        // Successful registration redirects to login and shows a one-time confirmation.
        $this->assertResponseRedirects('/login');
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[role="status"]', 'Your account has been created successfully');
    }

    public function testRegistrationRejectsDuplicateEmail(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Pre-create a user with this email
        $uniqueEmail = 'duplicate' . uniqid() . '@example.com';
        $existingUser = new User();// I have constantly, repeatedly problem with intelephense. Undefined type 'App\Entity\User'. P1009. This line is an example. What is the problem here? Is intelephense faulty?
        
        $existingUser->setEmail($uniqueEmail);
        $hashedPassword = $passwordHasher->hashPassword($existingUser, 'ExistingPassword123');
        $existingUser->setPassword($hashedPassword);
        
        $em->persist($existingUser);
        $em->flush();

        // Now try to register with the same email
        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Register')->form();
        $form['registration_form[email]'] = $uniqueEmail;
        $form['registration_form[plainPassword][first]'] = 'AnotherPassword123';
        $form['registration_form[plainPassword][second]'] = 'AnotherPassword123';
        $client->submit($form);

        // Should show validation error (422)
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('[role="alert"]', 'already in use');
    }

    public function testRegistrationRejectsInvalidEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $form = $crawler->selectButton('Register')->form();
        $form['registration_form[email]'] = 'not-an-email';
        $form['registration_form[plainPassword][first]'] = 'ValidPassword123';
        $form['registration_form[plainPassword][second]'] = 'ValidPassword123';

        $client->submit($form);

        // Should return 422 for validation error
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('[role="alert"]');
    }

    public function testRegistrationRejectsShortPassword(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $form = $crawler->selectButton('Register')->form();
        $form['registration_form[email]'] = 'short' . uniqid() . '@example.com';
        $form['registration_form[plainPassword][first]'] = 'short';
        $form['registration_form[plainPassword][second]'] = 'short';

        $client->submit($form);

        // Should return 422 for validation error
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('[role="alert"]');
    }

    public function testRegistrationRejectsEmptyEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $form = $crawler->selectButton('Register')->form();
        $form['registration_form[email]'] = '';
        $form['registration_form[plainPassword][first]'] = 'ValidPassword123';
        $form['registration_form[plainPassword][second]'] = 'ValidPassword123';

        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('[role="alert"]');
    }

    public function testRegistrationRejectsEmptyPassword(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $form = $crawler->selectButton('Register')->form();
        $form['registration_form[email]'] = 'empty' . uniqid() . '@example.com';
        $form['registration_form[plainPassword][first]'] = '';
        $form['registration_form[plainPassword][second]'] = '';

        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('[role="alert"]');
    }

    public function testUnauthenticatedUserCanAccessRegistration(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
    }
}

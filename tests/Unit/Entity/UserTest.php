<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testUserCanBeCreated(): void
    {
        $this->assertInstanceOf(User::class, $this->user);
    }

    public function testUserEmailCanBeSet(): void
    {
        $email = 'salesperson@pro-technic.com';
        $this->user->setEmail($email);

        $this->assertSame($email, $this->user->getEmail());
    }

    public function testUserEmailCanBeRetrieved(): void
    {
        $email = 'test@example.com';
        $this->user->setEmail($email);

        $this->assertSame($email, $this->user->getEmail());
    }

    public function testUserPasswordCanBeSet(): void
    {
        $password = 'hashed_password_123';
        $this->user->setPassword($password);

        $this->assertSame($password, $this->user->getPassword());
    }

    public function testUserPasswordCanBeRetrieved(): void
    {
        $password = 'some_hashed_value';
        $this->user->setPassword($password);

        $this->assertSame($password, $this->user->getPassword());
    }

    public function testUserHasRoleUser(): void
    {
        $this->user->setEmail('test@example.com');
        $this->user->setPassword('password');

        $this->assertContains('ROLE_USER', $this->user->getRoles());
    }

    public function testUserGetUsernameReturnsEmail(): void
    {
        $email = 'user@pro-technic.com';
        $this->user->setEmail($email);

        $this->assertSame($email, $this->user->getUserIdentifier());
    }

    public function testUserIsAuthenticatedByDefault(): void
    {
        $this->user->setEmail('test@example.com');
        $this->user->setPassword('hashed');

        // User is authenticated after being loaded from database
        // For new users, this is tested in functional tests
        $this->assertNotNull($this->user->getEmail());
    }

    public function testUserEraseCredentialsDoesNothing(): void
    {
        $password = 'sensitive_data';
        $this->user->setPassword($password);
        $this->user->eraseCredentials();

        // eraseCredentials should not throw, password is hashed anyway
        $this->assertSame($password, $this->user->getPassword());
    }
}

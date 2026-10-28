<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixture extends Fixture
{
    public const REFERENCE_PREFIX = 'user-';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail($_ENV['ADMIN_USER_EMAIL'] ?? 'admin@yahoo.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $_ENV['ADMIN_USER_PASSWORD'] ?? 'admin@yahoo.com'));
        $manager->persist($admin);
        // Adds a reference to the admin user so it can be retrieved later in other fixtures.
        $this->addReference(self::REFERENCE_PREFIX.'admin', $admin);

        $defaultUser = new User();
        $defaultUser->setEmail($_ENV['DEFAULT_USER_EMAIL'] ?? 'jedan@yahoo.com');
        $defaultUser->setRoles(['ROLE_USER']);
        $defaultUser->setPassword($this->passwordHasher->hashPassword($defaultUser, $_ENV['DEFAULT_USER_PASSWORD'] ?? 'jedan@yahoo.com'));
        $manager->persist($defaultUser);
        $this->addReference(self::REFERENCE_PREFIX.'default', $defaultUser);

        $manager->flush();
    }
}
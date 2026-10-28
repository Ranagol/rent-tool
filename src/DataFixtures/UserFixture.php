<?php

namespace App\DataFixtures;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixture extends Fixture
{
    public const REFERENCE_PREFIX = 'user-';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%env(ADMIN_USER_EMAIL)%')] private readonly string $adminEmail,
        #[Autowire('%env(ADMIN_USER_PASSWORD)%')] private readonly string $adminPassword,
        #[Autowire('%env(DEFAULT_USER_EMAIL)%')] private readonly string $defaultEmail,
        #[Autowire('%env(DEFAULT_USER_PASSWORD)%')] private readonly string $defaultPassword,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail($this->adminEmail);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $this->adminPassword));
        $manager->persist($admin);
        // Adds a reference to the admin user so it can be retrieved later in other fixtures.
        $this->addReference(self::REFERENCE_PREFIX.'admin', $admin);

        $defaultUser = new User();
        $defaultUser->setEmail($this->defaultEmail);
        $defaultUser->setRoles(['ROLE_USER']);
        $defaultUser->setPassword($this->passwordHasher->hashPassword($defaultUser, $this->defaultPassword));
        $manager->persist($defaultUser);
        $this->addReference(self::REFERENCE_PREFIX.'default', $defaultUser);

        $manager->flush();
    }
}

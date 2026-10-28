<?php

namespace App\DataFixtures;

use App\Entity\Tool;
use App\Entity\User;
use App\Factory\CustomerFactory;
use App\Factory\RentalFactory;
use App\Factory\ToolFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class RentalFixtures extends Fixture implements DependentFixtureInterface
{
    private const CLOSED_RENTALS = 20;
    private const ACTIVE_RENTALS = 5;
    private const USER_REFERENCES = ['admin', 'default'];

    public function getDependencies(): array
    {
        return [UserFixture::class, CustomerFixtures::class, ToolFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $tools = ToolFactory::all();

        $this->createClosedRentals($tools);
        $this->createActiveRentals($tools);
    }

    /**
     * Closed rentals follow each other in time, so a tool is never in two rentals at once.
     *
     * @param list<Tool> $tools
     */
    private function createClosedRentals(array $tools): void
    {
        $cursor = $this->now()->modify('-200 days');

        for ($i = 0; $i < self::CLOSED_RENTALS; ++$i) {
            $days = random_int(1, 5);
            $start = $cursor;
            $expectedReturn = $start->modify("+$days days");
            $actualReturn = $start->modify(sprintf('+%d hours', $days * 24 + random_int(-5, 10)));

            RentalFactory::new()
                ->closed($start, $expectedReturn, $actualReturn, $this->randomTools($tools, random_int(2, 4)))
                ->create(['customer' => CustomerFactory::random(), 'user' => $this->randomUser()]);

            // At most 5 + 3 days per rental, so all closed rentals end more than 40 days ago.
            $cursor = $actualReturn->modify(sprintf('+%d days', random_int(1, 3)));
        }
    }

    /**
     * Each active rental gets two tools of its own, because a tool can be in only one active rental.
     *
     * @param list<Tool> $tools
     */
    private function createActiveRentals(array $tools): void
    {
        shuffle($tools);

        for ($i = 0; $i < self::ACTIVE_RENTALS; ++$i) {
            $start = $this->now()->modify(sprintf('-%d days -%d hours', random_int(1, 3), random_int(0, 12)));
            $expectedReturn = $start->modify(sprintf('+%d days', random_int(5, 9)));

            RentalFactory::new()
                ->active($start, $expectedReturn, array_slice($tools, $i * 2, 2))
                ->create(['customer' => CustomerFactory::random(), 'user' => $this->randomUser()]);
        }
    }

    /**
     * @param list<Tool> $tools
     *
     * @return non-empty-list<Tool>
     */
    private function randomTools(array $tools, int $count): array
    {
        shuffle($tools);

        return array_slice($tools, 0, $count);
    }

    private function randomUser(): User
    {
        return $this->getReference(UserFixture::REFERENCE_PREFIX.self::USER_REFERENCES[array_rand(self::USER_REFERENCES)], User::class);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now')->setTime((int) date('H'), (int) date('i'));
    }
}

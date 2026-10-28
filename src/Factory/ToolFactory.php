<?php

namespace App\Factory;

use App\Entity\Tool;
use App\Entity\ToolStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Tool>
 */
final class ToolFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Tool::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->words(2, true),
            'serialNumber' => strtoupper(self::faker()->unique()->bothify('SN-????-#####')),
            'description' => self::faker()->optional(0.7)->sentence(),
            'imageUrl' => 'https://picsum.photos/seed/'.self::faker()->unique()->numberBetween(1, 100000).'/400/300',
            'comments' => self::faker()->optional(0.2)->sentence(),
            // Non-negative amount with two decimals, as the DECIMAL(10,2) column expects.
            'dailyRentCost' => number_format(self::faker()->randomFloat(2, 5, 80), 2, '.', ''),
            'status' => ToolStatus::Available,
        ];
    }
}

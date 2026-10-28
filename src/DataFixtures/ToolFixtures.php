<?php

namespace App\DataFixtures;

use App\Factory\ToolFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ToolFixtures extends Fixture
{
    private const NAMES = [
        'Cordless Drill',
        'Hammer Drill',
        'Angle Grinder',
        'Circular Saw',
        'Jigsaw',
        'Orbital Sander',
        'Pressure Washer',
        'Concrete Mixer',
        'Tile Cutter',
        'Wet Vacuum',
        'Hedge Trimmer',
    ];

    public function load(ObjectManager $manager): void
    {
        ToolFactory::new()->sequence(array_map(static fn (string $name): array => ['name' => $name], self::NAMES))->create();
    }
}

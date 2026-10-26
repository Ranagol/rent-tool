<?php

namespace App\Tests;

trait RefreshDatabaseTrait
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanDatabase();
    }

    protected function cleanDatabase(): void
    {
        try {
            $container = static::getContainer();
            $connection = $container->get('doctrine')->getConnection();
            $connection->executeStatement('TRUNCATE TABLE "user" CASCADE');
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }
    }
}

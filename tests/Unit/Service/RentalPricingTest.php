<?php

namespace App\Tests\Unit\Service;

use App\Entity\RentalItem;
use App\Service\RentalPricing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RentalPricingTest extends TestCase
{
    #[DataProvider('durations')]
    public function testBillableDays(string $interval, int $expectedDays): void
    {
        $start = new \DateTimeImmutable('2026-01-01 10:00:00');

        $this->assertSame($expectedDays, (new RentalPricing())->billableDays($start, $start->modify($interval)));
    }

    /** @return iterable<string, array{string, int}> */
    public static function durations(): iterable
    {
        yield 'immediate return' => ['+0 seconds', 1];
        yield '1 minute' => ['+1 minute', 1];
        yield '23 hours' => ['+23 hours', 1];
        yield '24 hours' => ['+24 hours', 1];
        yield '25 hours' => ['+25 hours', 2];
        yield '48 hours' => ['+48 hours', 2];
        yield '49 hours' => ['+49 hours', 3];
    }

    public function testTotalSumsItemsTimesDays(): void
    {
        $items = [
            (new RentalItem())->setDailyRentCost('12.50'),
            (new RentalItem())->setDailyRentCost('0.99'),
        ];
        $start = new \DateTimeImmutable('2026-01-01 10:00:00');

        $this->assertSame('40.47', (new RentalPricing())->total($items, $start, $start->modify('+49 hours')));
    }

    public function testTotalOfFreeItemsIsZero(): void
    {
        $start = new \DateTimeImmutable('2026-01-01 10:00:00');

        $this->assertSame('0.00', (new RentalPricing())->total([(new RentalItem())->setDailyRentCost('0.00')], $start, $start->modify('+1 day')));
    }
}

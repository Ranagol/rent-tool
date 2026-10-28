<?php

namespace App\Service;

use App\Entity\RentalItem;

final class RentalPricing
{
    private const SECONDS_PER_DAY = 86400;

    /** Started 24-hour periods, minimum 1. */
    public function billableDays(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $seconds = $end->getTimestamp() - $start->getTimestamp();

        return max(1, (int) ceil($seconds / self::SECONDS_PER_DAY));
    }

    /**
     * @param iterable<RentalItem> $items
     *
     * @return string decimal with 2 digits, e.g. "37.50"
     */
    public function total(iterable $items, \DateTimeImmutable $start, \DateTimeImmutable $end): string
    {
        $days = $this->billableDays($start, $end);
        $cents = 0;
        foreach ($items as $item) {
            $cents += self::toCents($item->getDailyRentCost()) * $days;
        }

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private static function toCents(?string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}

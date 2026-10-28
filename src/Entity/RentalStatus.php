<?php

namespace App\Entity;

enum RentalStatus: string
{
    case Active = 'active';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Returned => 'Returned',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge-info',
            self::Returned => 'badge-success',
            self::Cancelled => 'badge-danger',
        };
    }
}

<?php

namespace App\Entity;

enum ToolStatus: string
{
    case Available = 'available';
    case Rented = 'rented';
    case InRepair = 'in_repair';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Rented => 'Rented',
            self::InRepair => 'In repair',
            self::Retired => 'Retired',
        };
    }

    /** Value used by the `badge-*` CSS classes. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Available => 'badge-success',
            self::Rented => 'badge-info',
            self::InRepair => 'badge-warning',
            self::Retired => 'badge-danger',
        };
    }

    /**
     * Statuses a salesperson may set by hand; `rented` is managed by the rental workflow.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Available, self::InRepair, self::Retired];
    }
}

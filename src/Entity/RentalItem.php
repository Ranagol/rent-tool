<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rental_items')]
#[ORM\UniqueConstraint(name: 'UNIQ_RENTAL_ITEM_RENTAL_TOOL', fields: ['rental', 'tool'])]
class RentalItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Rental $rental = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Tool $tool = null;

    // Copied from the tool when rented, so later price changes do not alter history.
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $dailyRentCost = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRental(): ?Rental
    {
        return $this->rental;
    }

    public function setRental(?Rental $rental): static
    {
        $this->rental = $rental;

        return $this;
    }

    public function getTool(): ?Tool
    {
        return $this->tool;
    }

    public function setTool(?Tool $tool): static
    {
        $this->tool = $tool;

        return $this;
    }

    public function getDailyRentCost(): ?string
    {
        return $this->dailyRentCost;
    }

    public function setDailyRentCost(?string $dailyRentCost): static
    {
        $this->dailyRentCost = $dailyRentCost;

        return $this;
    }
}

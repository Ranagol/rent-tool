<?php

namespace App\Entity;

use App\Repository\RentalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: RentalRepository::class)]
#[ORM\Table(name: 'rentals')]
class Rental
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Please select a customer.')]
    private ?Customer $customer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $rentalStartDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $expectedReturnDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $actualReturnDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $totalRentCost = '0.00';

    #[ORM\Column(options: ['default' => false])]
    private bool $isCancelled = false;

    /** @var Collection<int, RentalItem> */
    #[ORM\OneToMany(targetEntity: RentalItem::class, mappedBy: 'rental', cascade: ['persist'])]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (null !== $this->rentalStartDate && null !== $this->expectedReturnDate
            && $this->expectedReturnDate <= $this->rentalStartDate) {
            $context->buildViolation('The expected return date must be later than the start date.')
                ->atPath('expectedReturnDate')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRentalStartDate(): ?\DateTimeImmutable
    {
        return $this->rentalStartDate;
    }

    public function setRentalStartDate(?\DateTimeImmutable $rentalStartDate): static
    {
        $this->rentalStartDate = $rentalStartDate;

        return $this;
    }

    public function getExpectedReturnDate(): ?\DateTimeImmutable
    {
        return $this->expectedReturnDate;
    }

    public function setExpectedReturnDate(?\DateTimeImmutable $expectedReturnDate): static
    {
        $this->expectedReturnDate = $expectedReturnDate;

        return $this;
    }

    public function getActualReturnDate(): ?\DateTimeImmutable
    {
        return $this->actualReturnDate;
    }

    public function setActualReturnDate(?\DateTimeImmutable $actualReturnDate): static
    {
        $this->actualReturnDate = $actualReturnDate;

        return $this;
    }

    public function getTotalRentCost(): string
    {
        return $this->totalRentCost;
    }

    public function setTotalRentCost(string $totalRentCost): static
    {
        $this->totalRentCost = $totalRentCost;

        return $this;
    }

    public function isCancelled(): bool
    {
        return $this->isCancelled;
    }

    public function setIsCancelled(bool $isCancelled): static
    {
        $this->isCancelled = $isCancelled;

        return $this;
    }

    public function isActive(): bool
    {
        return null === $this->actualReturnDate && !$this->isCancelled;
    }

    public function getStatus(): RentalStatus
    {
        return match (true) {
            $this->isCancelled => RentalStatus::Cancelled,
            null !== $this->actualReturnDate => RentalStatus::Returned,
            default => RentalStatus::Active,
        };
    }

    /** @return Collection<int, RentalItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(RentalItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setRental($this);
        }

        return $this;
    }
}

<?php

namespace App\Factory;

use App\Entity\Rental;
use App\Entity\RentalItem;
use App\Entity\Tool;
use App\Entity\ToolStatus;
use App\Service\RentalPricing;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Builds rentals that obey the README rules: at least one item, prices copied from the tools,
 * totals from {@see RentalPricing}, and tool statuses matching the rental state.
 * `customer` and `user` must be passed in; use {@see closed()} or {@see active()} to pick the state.
 *
 * @extends PersistentObjectFactory<Rental>
 */
final class RentalFactory extends PersistentObjectFactory
{
    public function __construct(private readonly RentalPricing $pricing)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Rental::class;
    }

    protected function defaults(): array
    {
        return [];
    }

    /**
     * A returned rental in the past. Its tools were returned in good condition, so they stay available.
     *
     * @param non-empty-list<Tool> $tools
     */
    public function closed(\DateTimeImmutable $start, \DateTimeImmutable $expectedReturn, \DateTimeImmutable $actualReturn, array $tools): static
    {
        return $this
            ->with([
                'rentalStartDate' => $start,
                'expectedReturnDate' => $expectedReturn,
                'actualReturnDate' => $actualReturn,
            ])
            ->withItems($tools, ToolStatus::Available);
    }

    /**
     * A rental that has started and is not returned yet; its tools are rented.
     *
     * @param non-empty-list<Tool> $tools
     */
    public function active(\DateTimeImmutable $start, \DateTimeImmutable $expectedReturn, array $tools): static
    {
        return $this
            ->with(['rentalStartDate' => $start, 'expectedReturnDate' => $expectedReturn])
            ->withItems($tools, ToolStatus::Rented);
    }

    /**
     * @param non-empty-list<Tool> $tools
     */
    private function withItems(array $tools, ToolStatus $toolStatus): static
    {
        return $this->afterInstantiate(function (Rental $rental) use ($tools, $toolStatus): void {
            foreach ($tools as $tool) {
                $rental->addItem((new RentalItem())->setTool($tool)->setDailyRentCost($tool->getDailyRentCost()));
                $tool->setStatus($toolStatus);
            }

            $rental->setTotalRentCost($this->pricing->total(
                $rental->getItems(),
                $rental->getRentalStartDate(),
                $rental->getActualReturnDate() ?? $rental->getExpectedReturnDate(),
            ));
        });
    }
}

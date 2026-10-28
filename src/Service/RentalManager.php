<?php

namespace App\Service;

use App\Entity\Rental;
use App\Entity\RentalItem;
use App\Entity\Tool;
use App\Entity\ToolStatus;
use App\Exception\RentalException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Clock\ClockInterface;

/** Rental workflow (README sections 5.5, 6 and 7); every operation runs in one transaction. */
final class RentalManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RentalPricing $pricing,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param list<Tool> $tools
     */
    public function create(Rental $rental, array $tools): void
    {
        if ([] === $tools) {
            throw new RentalException('Select at least one tool.');
        }

        $this->entityManager->wrapInTransaction(function () use ($rental, $tools): void {
            // Lock the rows so two salespeople cannot rent the same tool at once.
            foreach ($this->lockTools($tools) as $tool) {
                if (ToolStatus::Available !== $tool->getStatus()) {
                    throw new RentalException(sprintf('Tool %s is not available.', $tool->getName()));
                }

                $tool->setStatus(ToolStatus::Rented);
                $rental->addItem((new RentalItem())->setTool($tool)->setDailyRentCost($tool->getDailyRentCost()));
            }

            $rental->setTotalRentCost($this->pricing->total($rental->getItems(), $rental->getRentalStartDate(), $rental->getExpectedReturnDate()));
            $this->entityManager->persist($rental);
        });
    }

    /** Persists edited customer/dates and refreshes the estimated cost. */
    public function update(Rental $rental): void
    {
        $this->assertActive($rental);

        $rental->setTotalRentCost($this->pricing->total($rental->getItems(), $rental->getRentalStartDate(), $rental->getExpectedReturnDate()));
        $this->entityManager->flush();
    }

    /**
     * @param list<Tool> $toolsNeedingRepair
     */
    public function return(Rental $rental, array $toolsNeedingRepair): void
    {
        $this->assertActive($rental);

        $now = $this->clock->now();
        if ($now < $rental->getRentalStartDate()) {
            throw new RentalException('The rental has not started yet, so it cannot be returned.');
        }

        $repairIds = array_map(static fn (Tool $tool): ?int => $tool->getId(), $toolsNeedingRepair);

        $this->entityManager->wrapInTransaction(function () use ($rental, $now, $repairIds): void {
            $rental->setActualReturnDate($now);
            $rental->setTotalRentCost($this->pricing->total($rental->getItems(), $rental->getRentalStartDate(), $now));

            foreach ($rental->getItems() as $item) {
                $tool = $item->getTool();
                $tool->setStatus(in_array($tool->getId(), $repairIds, true) ? ToolStatus::InRepair : ToolStatus::Available);
            }
        });
    }

    /** The rental and its items are kept; the tools become available again. */
    public function cancel(Rental $rental): void
    {
        $this->assertActive($rental);

        $this->entityManager->wrapInTransaction(function () use ($rental): void {
            $rental->setIsCancelled(true);

            foreach ($rental->getItems() as $item) {
                $item->getTool()->setStatus(ToolStatus::Available);
            }
        });
    }

    private function assertActive(Rental $rental): void
    {
        if (!$rental->isActive()) {
            throw new RentalException('Only active rentals can be changed.');
        }
    }

    /**
     * @param list<Tool> $tools
     *
     * @return list<Tool>
     */
    private function lockTools(array $tools): array
    {
        $ids = array_map(static fn (Tool $tool): ?int => $tool->getId(), $tools);

        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Tool::class, 't')
            ->where('t.id IN (:ids)')
            ->orderBy('t.id')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
    }
}

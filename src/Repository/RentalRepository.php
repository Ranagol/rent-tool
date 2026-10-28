<?php

namespace App\Repository;

use App\Entity\Rental;
use App\Entity\RentalItem;
use App\Entity\RentalStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rental>
 */
class RentalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rental::class);
    }

    /**
     * @param string $sort DQL expression from a whitelist, e.g. "r.id" or "c.name"
     *
     * @return array{items: list<Rental>, total: int}
     */
    public function search(string $term, ?RentalStatus $status, string $sort, string $direction, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('r')
            ->join('r.customer', 'c')
            ->addSelect('c')
            ->orderBy($sort, 'desc' === $direction ? 'DESC' : 'ASC')
            ->addOrderBy('r.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ('' !== $term) {
            $qb->andWhere(sprintf(
                'LOWER(c.name) LIKE :term OR LOWER(c.email) LIKE :term OR EXISTS (SELECT 1 FROM %s ri JOIN ri.tool t WHERE ri.rental = r AND (LOWER(t.name) LIKE :term OR LOWER(t.serialNumber) LIKE :term))',
                RentalItem::class,
            ))->setParameter('term', '%'.mb_strtolower(addcslashes($term, '%_\\')).'%');
        }

        match ($status) {
            RentalStatus::Active => $qb->andWhere('r.actualReturnDate IS NULL AND r.isCancelled = false'),
            RentalStatus::Returned => $qb->andWhere('r.actualReturnDate IS NOT NULL AND r.isCancelled = false'),
            RentalStatus::Cancelled => $qb->andWhere('r.isCancelled = true'),
            null => null,
        };

        $paginator = new Paginator($qb);

        return ['items' => iterator_to_array($paginator), 'total' => count($paginator)];
    }

    /** @return list<Rental> */
    public function findByCustomer(int $customerId): array
    {
        return $this->createQueryBuilder('r')
            ->where('IDENTITY(r.customer) = :id')
            ->setParameter('id', $customerId)
            ->orderBy('r.rentalStartDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

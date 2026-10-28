<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * @return array{items: list<Customer>, total: int}
     */
    public function search(string $term, string $sort, string $direction, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.'.$sort, 'desc' === $direction ? 'DESC' : 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ('' !== $term) {
            $qb->andWhere('LOWER(c.name) LIKE :term OR LOWER(c.email) LIKE :term OR LOWER(c.phone) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(addcslashes($term, '%_\\')).'%');
        }

        $paginator = new Paginator($qb);

        return ['items' => iterator_to_array($paginator), 'total' => count($paginator)];
    }
}

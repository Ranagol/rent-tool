<?php

namespace App\Repository;

use App\Entity\Tool;
use App\Entity\ToolStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tool>
 */
class ToolRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tool::class);
    }

    /**
     * @return array{items: list<Tool>, total: int}
     */
    public function search(string $term, ?ToolStatus $status, string $sort, string $direction, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.'.$sort, 'desc' === $direction ? 'DESC' : 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ('' !== $term) {
            $qb->andWhere('LOWER(t.name) LIKE :term OR LOWER(t.serialNumber) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(addcslashes($term, '%_\\')).'%');
        }

        if (null !== $status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status);
        }

        $paginator = new Paginator($qb);

        return ['items' => iterator_to_array($paginator), 'total' => count($paginator)];
    }
}

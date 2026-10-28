<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return array{items: list<User>, total: int}
     */
    public function search(string $term, string $sort, string $direction, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.'.$sort, 'desc' === $direction ? 'DESC' : 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ('' !== $term) {
            $qb->andWhere('LOWER(u.email) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(addcslashes($term, '%_\\')).'%');
        }

        $paginator = new Paginator($qb);

        return ['items' => iterator_to_array($paginator), 'total' => count($paginator)];
    }
}

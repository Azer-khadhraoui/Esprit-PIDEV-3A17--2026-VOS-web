<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * @return list<string>
     */
    public function findClientEmails(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.email')
            ->where('u.role = :role')
            ->andWhere('u.email IS NOT NULL')
            ->andWhere("u.email <> ''")
            ->setParameter('role', 'CLIENT')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_unique(array_map(
            static fn (array $row): string => (string) ($row['email'] ?? ''),
            $rows,
        )));
    }

    public function findActiveByResetTokenHash(string $tokenHash): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.resetTokenHash = :tokenHash')
            ->andWhere('u.resetExpiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

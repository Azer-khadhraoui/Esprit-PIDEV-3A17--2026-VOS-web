<?php

namespace App\Repository;

use App\Entity\EvaluationEntretien;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EvaluationEntretienRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EvaluationEntretien::class);
    }

    public function findWithFilters(
        ?string $search = null,
        ?string $decision = null,
        string $sortBy = 'ev.scoreTest',
        string $sortDir = 'DESC'
    ): array {
        $allowed = ['ev.scoreTest', 'ev.noteEntretien', 'ev.decision'];
        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = 'ev.scoreTest';
        }

        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $qb = $this->createQueryBuilder('ev');

        if ($search) {
            $qb->andWhere('ev.commentaire LIKE :search OR ev.decision LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($decision) {
            $qb->andWhere('ev.decision = :decision')->setParameter('decision', $decision);
        }

        return $qb->orderBy($sortBy, $sortDir)->getQuery()->getResult();
    }

    public function findForUser(int $userId): array
    {
        return $this->createQueryBuilder('ev')
            ->innerJoin('ev.entretien', 'e')
            ->andWhere('e.idUtilisateur = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('ev.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findForEntretienIds(array $entretienIds): array
    {
        if ([] === $entretienIds) {
            return [];
        }

        return $this->createQueryBuilder('ev')
            ->innerJoin('ev.entretien', 'e')
            ->andWhere('e.id IN (:ids)')
            ->setParameter('ids', $entretienIds)
            ->orderBy('ev.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

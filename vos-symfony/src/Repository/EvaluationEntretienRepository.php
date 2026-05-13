<?php

namespace App\Repository;

use App\Entity\Candidature;
use App\Entity\EvaluationEntretien;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EvaluationEntretien>
 */
class EvaluationEntretienRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EvaluationEntretien::class);
    }

    /**
     * @return array<int, EvaluationEntretien>
     */
    public function findWithFilters(
        ?string $search = null,
        ?string $decision = null,
        string $sortBy = 'ev.scoreTest',
        string $sortDir = 'DESC',
        int $limit = 200,
        int $offset = 0,
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

        return $qb->orderBy($sortBy, $sortDir)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, EvaluationEntretien>
     */
    public function findForUser(int $userId, int $limit = 500): array
    {
        return $this->createQueryBuilder('ev')
            ->innerJoin('ev.entretien', 'e')
            ->leftJoin(Candidature::class, 'c', 'WITH', 'c.id_candidature = e.idCandidature')
            ->andWhere('(e.idUtilisateur = :userId OR c.id_utilisateur = :userId)')
            ->setParameter('userId', $userId)
            ->orderBy('ev.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<int, int> $entretienIds
     * @return array<int, EvaluationEntretien>
     */
    public function findForEntretienIds(array $entretienIds, int $limit = 2000): array
    {
        if ([] === $entretienIds) {
            return [];
        }

        return $this->createQueryBuilder('ev')
            ->innerJoin('ev.entretien', 'e')
            ->andWhere('e.id IN (:ids)')
            ->setParameter('ids', $entretienIds)
            ->orderBy('ev.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

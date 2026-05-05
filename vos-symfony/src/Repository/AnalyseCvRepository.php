<?php

namespace App\Repository;

use App\Entity\AnalyseCv;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnalyseCv>
 *
 * @method AnalyseCv|null find($id, $lockMode = null, $lockVersion = null)
 * @method AnalyseCv|null findOneBy(array $criteria, array $orderBy = null)
 * @method AnalyseCv[]    findAll()
 * @method AnalyseCv[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AnalyseCvRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyseCv::class);
    }

    /**
     * Find the latest analysis for a specific candidature
     */
    public function findLatestByCandidature(int $idCandidature): ?AnalyseCv
    {
        return $this->createQueryBuilder('a')
            ->andWhere('IDENTITY(a.candidature) = :id')
            ->setParameter('id', $idCandidature)
            ->orderBy('a.date_analyse', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all analyses for a specific candidature
     */
    public function findByCandidature(int $idCandidature, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('IDENTITY(a.candidature) = :id')
            ->setParameter('id', $idCandidature)
            ->orderBy('a.date_analyse', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find analyses with scores higher than a threshold
     */
    public function findByScoreGreaterThan(int $minScore, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.score_cv >= :minScore')
            ->setParameter('minScore', $minScore)
            ->orderBy('a.score_cv', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

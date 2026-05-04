<?php

namespace App\Repository;

use App\Entity\ContratEmbauche;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContratEmbaucheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContratEmbauche::class);
    }

    /**
     * @return ContratEmbauche[]
     */
    public function findEndingBetween(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.dateFin BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('c.dateFin', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

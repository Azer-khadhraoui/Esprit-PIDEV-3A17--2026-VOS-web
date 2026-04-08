<?php

namespace App\Repository;

use App\Entity\Entretien;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EntretienRepository extends ServiceEntityRepository
{
    private const TYPE_CONDITION = 'e.typeEntretien = :type';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entretien::class);
    }

    public function findWithFilters(
        ?string $search = null,
        ?string $type = null,
        ?string $statut = null,
        string $sortBy = 'e.dateEntretien',
        string $sortDir = 'DESC'
    ): array {
        $allowed = ['e.dateEntretien', 'e.typeEntretien', 'e.statutEntretien', 'e.lieu'];
        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = 'e.dateEntretien';
        }

        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $qb = $this->createQueryBuilder('e');

        if ($search) {
            // Si la recherche est un nombre, chercher par ID aussi
            if (is_numeric($search)) {
                $qb->andWhere('e.id = :id OR e.lieu LIKE :search OR e.typeTest LIKE :search OR e.typeEntretien LIKE :search OR e.statutEntretien LIKE :search')
                    ->setParameter('id', (int) $search)
                    ->setParameter('search', '%' . $search . '%');
            } else {
                $qb->andWhere('e.lieu LIKE :search OR e.typeTest LIKE :search OR e.typeEntretien LIKE :search OR e.statutEntretien LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
            }
        }

        if ($type) {
            $qb->andWhere(self::TYPE_CONDITION)->setParameter('type', $type);
        }

        if ($statut) {
            $qb->andWhere('e.statutEntretien = :statut')->setParameter('statut', $statut);
        }

        return $qb->orderBy($sortBy, $sortDir)->getQuery()->getResult();
    }

    public function findForUser(
        int $userId,
        ?string $search = null,
        ?string $type = null,
        ?string $statut = null,
        string $sortBy = 'e.dateEntretien',
        string $sortDir = 'DESC'
    ): array {
        $allowed = ['e.dateEntretien', 'e.typeEntretien', 'e.statutEntretien', 'e.lieu'];
        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = 'e.dateEntretien';
        }

        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.idUtilisateur = :userId')
            ->setParameter('userId', $userId);

        if ($search) {
            // Si la recherche est un nombre, chercher par ID aussi
            if (is_numeric($search)) {
                $qb->andWhere('e.id = :id OR e.lieu LIKE :search OR e.typeTest LIKE :search OR e.typeEntretien LIKE :search OR e.statutEntretien LIKE :search')
                    ->setParameter('id', (int) $search)
                    ->setParameter('search', '%' . $search . '%');
            } else {
                $qb->andWhere('e.lieu LIKE :search OR e.typeTest LIKE :search OR e.typeEntretien LIKE :search OR e.statutEntretien LIKE :search')
                    ->setParameter('search', '%' . $search . '%');
            }
        }

        if ($type) {
            $qb->andWhere(self::TYPE_CONDITION)->setParameter('type', $type);
        }

        if ($statut) {
            $qb->andWhere('e.statutEntretien = :statut')->setParameter('statut', $statut);
        }

        return $qb->orderBy($sortBy, $sortDir)->getQuery()->getResult();
    }

    public function findForStats(
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?string $type = null
    ): array {
        $qb = $this->createQueryBuilder('e');

        if ($dateDebut) {
            $qb->andWhere('e.dateEntretien >= :debut')
                ->setParameter('debut', new \DateTime($dateDebut));
        }

        if ($dateFin) {
            $qb->andWhere('e.dateEntretien <= :fin')
                ->setParameter('fin', new \DateTime($dateFin));
        }

        if ($type) {
            $qb->andWhere(self::TYPE_CONDITION)
                ->setParameter('type', $type);
        }

        return $qb->orderBy('e.dateEntretien', 'ASC')->getQuery()->getResult();
    }
}

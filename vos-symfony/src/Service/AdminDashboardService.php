<?php

namespace App\Service;

use App\Dto\Admin\RoleStatDto;
use App\Repository\UserRepository;

class AdminDashboardService
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function getUsers(string $search = '', string $sortBy = 'id', string $sortOrder = 'DESC', string $roleFilter = '', int $maxResults = 100): array
    {
        $qb = $this->userRepository->createQueryBuilder('u');
        $allowedSortFields = [
            'id' => 'u.id',
            'nom' => 'u.nom',
            'prenom' => 'u.prenom',
            'email' => 'u.email',
            'role' => 'u.role',
        ];

        $sortBy = array_key_exists($sortBy, $allowedSortFields) ? $sortBy : 'id';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        if (!empty($search)) {
            $qb->where('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($roleFilter !== '') {
            $qb->andWhere('u.role = :roleFilter')
                ->setParameter('roleFilter', $roleFilter);
        }

        if ($maxResults < 1) {
            $maxResults = 100;
        }
        if ($maxResults > 500) {
            $maxResults = 500;
        }

        $qb->orderBy($allowedSortFields[$sortBy], $sortOrder)
            ->setMaxResults($maxResults);

        return $qb->getQuery()->getResult();
    }

    public function getStats(): array
    {
        return [
            'total' => $this->userRepository->count([]),
            'clients' => $this->userRepository->count(['role' => 'CLIENT']),
            'admins' => $this->userRepository->count(['role' => 'ADMIN_RH'])
                + $this->userRepository->count(['role' => 'ADMIN_TECHNIQUE']),
        ];
    }

    /**
     * @return array<int, array{label: string, total: int}>
     */
    public function getRoleStats(): array
    {
        $rows = $this->userRepository->createQueryBuilder('u')
            ->select('NEW App\\Dto\\Admin\\RoleStatDto(COALESCE(u.role, :na), COUNT(u.id))')
            ->setParameter('na', 'N/A')
            ->groupBy('u.role')
            ->orderBy('COUNT(u.id)', 'DESC') 
            ->getQuery()->getResult();

        return array_map(static function (RoleStatDto $row): array {
            return [
                'label' => $row->label,
                'total' => $row->total,
            ];
        }, $rows);
    }
}

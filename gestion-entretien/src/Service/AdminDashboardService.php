<?php

namespace App\Service;

use App\Repository\UserRepository;

class AdminDashboardService
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function getUsers(string $search = '', string $sortBy = 'id', string $sortOrder = 'DESC', string $roleFilter = ''): array
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

        $qb->orderBy($allowedSortFields[$sortBy], $sortOrder);

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
            ->select('u.role AS label, COUNT(u.id) AS total')
            ->groupBy('u.role')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $row): array {
            return [
                'label' => (string) ($row['label'] ?? 'N/A'),
                'total' => (int) ($row['total'] ?? 0),
            ];
        }, $rows);
    }
}

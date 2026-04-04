<?php

namespace App\Service;

use App\Repository\UserRepository;

class AdminDashboardService
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function getUsers(string $search = '', string $sortBy = 'id', string $sortOrder = 'DESC'): array
    {
        $qb = $this->userRepository->createQueryBuilder('u');

        if (!empty($search)) {
            $qb->where('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('u.' . $sortBy, $sortOrder);

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
}

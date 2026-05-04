<?php

namespace App\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DebugDbController extends AbstractController
{
    #[Route('/_debug/db', name: 'debug_db', methods: ['GET'])]
    public function index(ManagerRegistry $doctrine): JsonResponse
    {
        try {
            $conn = $doctrine->getConnection();
            $value = $conn->fetchOne('SELECT 1');
            return new JsonResponse(['ok' => true, 'result' => $value]);
        } catch (\Throwable $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}

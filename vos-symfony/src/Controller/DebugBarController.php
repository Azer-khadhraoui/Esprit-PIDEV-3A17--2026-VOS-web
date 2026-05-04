<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DebugBarController extends AbstractController
{
    #[Route('/_debug/bar', name: 'debug_bar', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $file = $projectDir . '/var/debug_bar.json';

        if (!file_exists($file)) {
            return new JsonResponse(['items' => []]);
        }

        $content = @file_get_contents($file);
        if (!$content) {
            return new JsonResponse(['items' => []]);
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return new JsonResponse(['items' => []]);
        }

        return new JsonResponse(['items' => $data]);
    }
}

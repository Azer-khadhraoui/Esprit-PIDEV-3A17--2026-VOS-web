<?php

namespace App\Controller;

use App\Service\ChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/chatbot', name: 'api_chatbot_')]
class ChatbotController extends AbstractController
{
    public function __construct(private ChatbotService $chatbotService) {}

    #[Route('/chat', name: 'chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['message']) || empty($data['message'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Message requis'
                ], Response::HTTP_BAD_REQUEST);
            }

            $message = $data['message'];
            $conversationHistory = $data['history'] ?? [];
            $user = $this->getUser();

            // Appel au service chatbot avec l'utilisateur connecté
            $result = $this->chatbotService->chat($message, $conversationHistory, $user);

            if ($result['success']) {
                return $this->json([
                    'success' => true,
                    'response' => $result['response'],
                    'message' => $result['message']
                ]);
            }

            return $this->json([
                'success' => false,
                'error' => $result['error'] ?? 'Erreur inconnue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur serveur: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/suggestions', name: 'suggestions', methods: ['GET'])]
    public function suggestions(Request $request): JsonResponse
    {
        try {
            $query = $request->query->get('q', '');

            if (empty($query)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Paramètre q requis'
                ], Response::HTTP_BAD_REQUEST);
            }

            $suggestions = $this->chatbotService->getSuggestions($query);

            return $this->json([
                'success' => true,
                'suggestions' => $suggestions
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'message' => 'Chatbot service is running'
        ]);
    }
}

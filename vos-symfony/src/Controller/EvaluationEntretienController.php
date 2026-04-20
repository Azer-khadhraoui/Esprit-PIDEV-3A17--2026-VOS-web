<?php

namespace App\Controller;

use App\Entity\EvaluationEntretien;
use App\Exception\AnthropicApiException;
use App\Form\EvaluationEntretienType;
use App\Repository\EntretienRepository;
use App\Repository\EvaluationEntretienRepository;
use App\Service\EntretienNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/evaluation/entretien')]
class EvaluationEntretienController extends AbstractController
{
    #[Route('/', name: 'app_evaluation_entretien_index', methods: ['GET'])]
    public function index(Request $request, EvaluationEntretienRepository $repo, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $search = (string) $request->query->get('search', '');
        $decision = (string) $request->query->get('decision', '');
        $sortBy = (string) $request->query->get('sortBy', 'ev.scoreTest');
        $sortDir = (string) $request->query->get('sortDir', 'DESC');

        return $this->render('evaluation_entretien/index.html.twig', [
            'evaluation_entretiens' => $repo->findWithFilters($search, $decision, $sortBy, $sortDir),
            'search' => $search,
            'decision' => $decision,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    #[Route('/new', name: 'app_evaluation_entretien_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, EntretienRepository $entretienRepository, EntretienNotificationService $notificationService, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $evaluation = new EvaluationEntretien();
        $entretienId = $request->query->get('entretienId');

        if ($entretienId) {
            $entretien = $entretienRepository->find($entretienId);
            if ($entretien) {
                $evaluation->setEntretien($entretien);
            }
        }

        $form = $this->createForm(EvaluationEntretienType::class, $evaluation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($evaluation);
            $entityManager->flush();

            try {
                $sentCount = $notificationService->notifyEvaluationCreated($evaluation);
                if ($sentCount > 0) {
                    $this->addFlash('success', sprintf('Notification email evaluation envoyee a %d destinataire(s).', $sentCount));
                } else {
                    $this->addFlash('warning', 'Evaluation enregistree, mais aucun email destinataire trouve.');
                }
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Evaluation enregistree, mais echec de notification email.');
            }

            $this->addFlash('success', 'Évaluation ajoutée avec succès.');

            return $this->redirectToRoute('app_evaluation_entretien_index');
        }

        return $this->render('evaluation_entretien/new.html.twig', [
            'form' => $form->createView(),
            'evaluation_entretien' => $evaluation,
        ]);
    }

    #[Route('/generate-comment', name: 'app_evaluation_entretien_generate_comment', methods: ['POST'])]
    public function generateComment(
        Request $request,
        SessionInterface $session,
        #[Autowire(env: 'GROQ_API_KEY')] string $groqApiKey,
    ): JsonResponse {
        if (!$session->get('admin_user_id')) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        try {
            $comment = $this->doGenerateComment($request, $groqApiKey);
            return new JsonResponse(['comment' => $comment]);
        } catch (AnthropicApiException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    private function doGenerateComment(Request $request, string $groqApiKey): string
    {
        if ('' === trim($groqApiKey)) {
            throw new AnthropicApiException('Clé API Groq non configurée.', 500);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        $prompt = sprintf(
            "Tu es un expert RH. Génère un commentaire d'évaluation professionnel en français pour un candidat avec ces résultats :\n" .
            "- Compétences techniques : %d/5\n" .
            "- Compétences comportementales : %d/5\n" .
            "- Communication : %d/5\n" .
            "- Motivation : %d/5\n" .
            "- Expérience : %d/5\n" .
            "- Score global : %.1f%%\n" .
            "- Note entretien : %d/5\n" .
            "- Décision : %s\n\n" .
            "Écris 2 à 3 phrases, professionnelles et constructives. Ne retourne que le commentaire, sans titre ni introduction.",
            (int)   ($payload['competencesTechniques']       ?? 0),
            (int)   ($payload['competencesComportementales'] ?? 0),
            (int)   ($payload['communication']               ?? 0),
            (int)   ($payload['motivation']                  ?? 0),
            (int)   ($payload['experience']                  ?? 0),
            (float) ($payload['scoreTest']                   ?? 0),
            (int)   ($payload['noteEntretien']               ?? 0),
            ($payload['decision'] ?? '') ?: 'Non définie',
        );

        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_POST           => true,
            \CURLOPT_TIMEOUT        => 30,
            \CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $groqApiKey,
            ],
            \CURLOPT_POSTFIELDS => json_encode([
                'model'      => 'llama-3.3-70b-versatile',
                'max_tokens' => 300,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]),
        ]);

        $raw       = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (false === $raw || '' !== $curlError) {
            throw new AnthropicApiException('Erreur réseau : ' . $curlError, 502);
        }

        $apiResponse = json_decode($raw, true);

        if (isset($apiResponse['error'])) {
            throw new AnthropicApiException($apiResponse['error']['message'] ?? 'Erreur API Groq.', 502);
        }

        $comment = $apiResponse['choices'][0]['message']['content'] ?? null;
        if (null === $comment) {
            throw new AnthropicApiException("Réponse inattendue de l'API Groq.", 502);
        }

        return trim($comment);
    }

    #[Route('/{id}', name: 'app_evaluation_entretien_show', methods: ['GET'])]
    public function show(EvaluationEntretien $evaluationEntretien, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        return $this->render('evaluation_entretien/show.html.twig', [
            'evaluation_entretien' => $evaluationEntretien,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evaluation_entretien_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EvaluationEntretien $evaluationEntretien, EntityManagerInterface $entityManager, EntretienNotificationService $notificationService, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $form = $this->createForm(EvaluationEntretienType::class, $evaluationEntretien);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            try {
                $sentCount = $notificationService->notifyEvaluationUpdated($evaluationEntretien);
                if ($sentCount > 0) {
                    $this->addFlash('success', sprintf('Notification de mise a jour evaluation envoyee a %d destinataire(s).', $sentCount));
                }
            } catch (\Throwable) {
                $this->addFlash('warning', 'Evaluation modifiee, mais echec de notification email.');
            }

            $this->addFlash('success', 'Évaluation modifiée avec succès.');

            return $this->redirectToRoute('app_evaluation_entretien_index');
        }

        return $this->render('evaluation_entretien/edit.html.twig', [
            'form' => $form->createView(),
            'evaluation_entretien' => $evaluationEntretien,
        ]);
    }

    #[Route('/{id}', name: 'app_evaluation_entretien_delete', methods: ['POST'])]
    public function delete(Request $request, EvaluationEntretien $evaluationEntretien, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        if ($this->isCsrfTokenValid('delete' . $evaluationEntretien->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($evaluationEntretien);
            $entityManager->flush();
            $this->addFlash('success', 'Évaluation supprimée.');
        }

        return $this->redirectToRoute('app_evaluation_entretien_index');
    }

    private function requireAdmin(SessionInterface $session): RedirectResponse|null
    {
        $adminId = $session->get('admin_user_id');
        $adminRole = (string) $session->get('admin_user_role', '');

        if (!$adminId && !str_starts_with($adminRole, 'ADMIN')) {
            $this->addFlash('error', 'Veuillez vous connecter en administrateur.');
            return $this->redirectToRoute('app_signin');
        }

        return null;
    }
}

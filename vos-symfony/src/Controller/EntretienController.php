<?php

namespace App\Controller;

use App\Entity\Entretien;
use App\Entity\OffreEmploi;
use App\Exception\AnthropicApiException;
use App\Form\EntretienType;
use App\Repository\CandidatureRepository;
use App\Repository\EntretienRepository;
use App\Repository\EvaluationEntretienRepository;
use App\Service\EntretienNotificationService;
use App\Service\GoogleCalendarService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

#[Route('/gestion-entretien')]
class EntretienController extends AbstractController
{
    private const MSG_NON_AUTORISE = 'Non autorise';
    #[Route('/', name: 'gestion_entretien_dashboard', methods: ['GET'])]
    public function index(Request $request, EntretienRepository $repo, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $search = (string) $request->query->get('search', '');
        $type = (string) $request->query->get('type', '');
        $statut = (string) $request->query->get('statut', '');
        $sortBy = (string) $request->query->get('sortBy', 'e.dateEntretien');
        $sortDir = (string) $request->query->get('sortDir', 'DESC');

        return $this->render('gestion_entretien/index.html.twig', [
            'entretiens' => $repo->findWithFilters($search, $type, $statut, $sortBy, $sortDir),
            'search' => $search,
            'type' => $type,
            'statut' => $statut,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    #[Route('/stats', name: 'gestion_entretien_stats', methods: ['GET'])]
    public function stats(Request $request, EntretienRepository $entretienRepository, EvaluationEntretienRepository $evaluationRepository, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        [$dateDebut, $dateFin, $typeFilter, $periode] = $this->resolveStatsFilters($request);
        $entretiens = $entretienRepository->findForStats($dateDebut, $dateFin, $typeFilter);
        $entretienIds = array_map(static fn (Entretien $entretien): ?int => $entretien->getId(), $entretiens);
        $entretienIds = array_values(array_filter($entretienIds, static fn (?int $id): bool => null !== $id));
        $evaluations = $evaluationRepository->findForEntretienIds($entretienIds);

        $entretienStats = $this->calculateEntretienStats($entretiens);
        $evaluationStats = $this->calculateEvaluationStats($evaluations);

        return $this->render('gestion_entretien/stats.html.twig', [
            ...$entretienStats,
            ...$evaluationStats,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'typeFilter' => $typeFilter,
            'periode' => $periode,
        ]);
    }

    #[Route('/new', name: 'gestion_entretien_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, EntretienNotificationService $notificationService, GoogleCalendarService $calendar, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $entretien = new Entretien();
        $form = $this->createForm(EntretienType::class, $entretien);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($entretien);
            $entityManager->flush();

            try {
                $eventId = $calendar->createEvent($entretien);
                $entretien->setCalendarEventId($eventId);
                $entityManager->flush();
                $this->addFlash('success', 'Événement ajouté au Google Calendar.');
            } catch (\Throwable) {
                $this->addFlash('warning', 'Entretien enregistré, mais échec de synchronisation Google Calendar.');
            }

            try {
                $sentCount = $notificationService->notifyEntretienCreated($entretien);
                if ($sentCount > 0) {
                    $this->addFlash('success', sprintf('Notification email envoyee a %d destinataire(s).', $sentCount));
                } else {
                    $this->addFlash('warning', 'Entretien enregistre, mais aucun email destinataire trouve.');
                }
            } catch (\Throwable) {
                $this->addFlash('warning', 'Entretien enregistre, mais echec de notification email.');
            }

            $this->addFlash('success', 'Entretien ajouté avec succès.');

            return $this->redirectToRoute('gestion_entretien_dashboard');
        }

        return $this->render('gestion_entretien/new.html.twig', [
            'form' => $form->createView(),
            'entretien' => $entretien,
        ]);
    }

    #[Route('/candidature-info/{id}', name: 'gestion_entretien_candidature_info', methods: ['GET'])]
    public function candidatureInfo(int $id, CandidatureRepository $candidatureRepo, EntityManagerInterface $em, SessionInterface $session): JsonResponse
    {
        if (!$session->get('admin_user_id')) {
            return new JsonResponse(['error' => self::MSG_NON_AUTORISE], 403);
        }

        $candidature = $candidatureRepo->find($id);
        if (null === $candidature) {
            return new JsonResponse(['error' => 'Candidature introuvable'], 404);
        }

        $titrePoster = $candidature->getDernierPoste() ?? '';
        $descriptionOffre = '';

        if (null !== $candidature->getIdOffre()) {
            $offre = $em->find(OffreEmploi::class, $candidature->getIdOffre());
            if (null !== $offre) {
                $titrePoster = $offre->getTitre() ?? $titrePoster;
                $descriptionOffre = $offre->getDescription() ?? '';
            }
        }

        return new JsonResponse([
            'poste' => $titrePoster,
            'domaine' => $candidature->getDomaineExperience() ?? '',
            'niveau' => $candidature->getNiveauExperience() ?? '',
            'annees' => $candidature->getAnneesExperience() ?? 0,
            'description_offre' => $descriptionOffre,
        ]);
    }

    #[Route('/generate-questions', name: 'gestion_entretien_generate_questions', methods: ['POST'])]
    public function generateQuestions(
        Request $request,
        CandidatureRepository $candidatureRepo,
        EntityManagerInterface $em,
        SessionInterface $session,
        #[Autowire(env: 'GROQ_API_KEY')] string $groqApiKey,
    ): JsonResponse {
        if (!$session->get('admin_user_id')) {
            return new JsonResponse(['error' => self::MSG_NON_AUTORISE], 403);
        }

        try {
            $questions = $this->doGenerateQuestions($request, $candidatureRepo, $em, $groqApiKey);
            return new JsonResponse(['questions' => $questions]);
        } catch (AnthropicApiException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    private function doGenerateQuestions(
        Request $request,
        CandidatureRepository $candidatureRepo,
        EntityManagerInterface $em,
        string $groqApiKey,
    ): string {
        if ('' === trim($groqApiKey)) {
            throw new AnthropicApiException('Cle API Groq non configuree dans .env (GROQ_API_KEY).', 500);
        }

        $payload = json_decode($request->getContent(), true);
        $candidatureId = (int) ($payload['candidatureId'] ?? 0);
        $typeEntretien = trim((string) ($payload['typeEntretien'] ?? ''));

        if ($candidatureId <= 0 || '' === $typeEntretien) {
            throw new AnthropicApiException('Parametres manquants.', 400);
        }

        $candidature = $candidatureRepo->find($candidatureId);
        if (null === $candidature) {
            throw new AnthropicApiException('Candidature introuvable.', 404);
        }

        $prompt = $this->buildQuestionsPrompt($candidature, $typeEntretien, $em);
        return $this->callGroqApi($groqApiKey, $prompt);
    }

    private function buildQuestionsPrompt(
        \App\Entity\Candidature $candidature,
        string $typeEntretien,
        EntityManagerInterface $em,
    ): string {
        $notSpecified = 'Non specifie';
        $poste = $candidature->getDernierPoste() ?? $notSpecified;
        $domaine = $candidature->getDomaineExperience() ?? $notSpecified;
        $niveau = $candidature->getNiveauExperience() ?? $notSpecified;
        $annees = $candidature->getAnneesExperience() ?? 0;

        if (null !== $candidature->getIdOffre()) {
            $offre = $em->find(OffreEmploi::class, $candidature->getIdOffre());
            if (null !== $offre && null !== $offre->getTitre()) {
                $poste = $offre->getTitre();
            }
        }

        $questionStyle = $typeEntretien === 'TECHNIQUE'
            ? 'techniques et de resolution de problemes'
            : 'comportementales, motivationnelles et sur les soft skills';

        return sprintf(
            "Tu es un expert en ressources humaines et recrutement.\n" .
            "Genere exactement 10 questions d'entretien pertinentes pour le profil suivant :\n\n" .
            "- Poste vise : %s\n- Domaine : %s\n- Niveau d'experience : %s (%d ans)\n- Type d'entretien : %s\n\n" .
            "Regles : retourne uniquement les 10 questions numerotees (1. ... 2. ...), une question par ligne, " .
            "sans introduction ni conclusion. Adapte les questions au type d'entretien (%s = questions %s).",
            $poste, $domaine, $niveau, $annees, $typeEntretien, $typeEntretien, $questionStyle
        );
    }

    private function callGroqApi(string $apiKey, string $prompt): string
    {
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_POST => true,
            \CURLOPT_TIMEOUT => 30,
            \CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            \CURLOPT_POSTFIELDS => json_encode([
                'model' => 'llama-3.3-70b-versatile',
                'max_tokens' => 1500,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]),
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (false === $raw || '' !== $curlError) {
            throw new AnthropicApiException('Erreur reseau : ' . $curlError, 502);
        }

        $apiResponse = json_decode($raw, true);

        if (isset($apiResponse['error'])) {
            throw new AnthropicApiException($apiResponse['error']['message'] ?? 'Erreur API Groq.', 502);
        }

        $questions = $apiResponse['choices'][0]['message']['content'] ?? null;
        if (null === $questions) {
            throw new AnthropicApiException('Reponse inattendue de l API Groq.', 502);
        }

        return $questions;
    }

    #[Route('/search', name: 'gestion_entretien_search', methods: ['GET'])]
    public function search(Request $request, EntretienRepository $repo, SessionInterface $session, CsrfTokenManagerInterface $csrf): JsonResponse
    {
        if (!$session->get('admin_user_id')) {
            return new JsonResponse(['error' => self::MSG_NON_AUTORISE], 403);
        }

        $search = (string) $request->query->get('search', '');
        $type = (string) $request->query->get('type', '');
        $statut = (string) $request->query->get('statut', '');
        $sortBy = (string) $request->query->get('sortBy', 'e.dateEntretien');
        $sortDir = (string) $request->query->get('sortDir', 'DESC');

        $entretiens = $repo->findWithFilters($search, $type, $statut, $sortBy, $sortDir);

        $data = array_map(function (Entretien $e) use ($csrf): array {
            return [
                'id' => $e->getId(),
                'dateEntretien' => $e->getDateEntretien()?->format('d/m/Y'),
                'heureEntretien' => $e->getHeureEntretien()?->format('H:i'),
                'typeEntretien' => $e->getTypeEntretien(),
                'typeTest' => $e->getTypeTest(),
                'statutEntretien' => $e->getStatutEntretien(),
                'lieu' => $e->getLieu(),
                'urlShow' => $this->generateUrl('gestion_entretien_show', ['id' => $e->getId()]),
                'urlEdit' => $this->generateUrl('gestion_entretien_edit', ['id' => $e->getId()]),
                'urlEval' => $this->generateUrl('app_evaluation_entretien_new', ['entretienId' => $e->getId()]),
                'urlDelete' => $this->generateUrl('gestion_entretien_delete', ['id' => $e->getId()]),
                'csrfDelete' => $csrf->getToken('delete' . $e->getId())->getValue(),
            ];
        }, $entretiens);

        return new JsonResponse(['entretiens' => $data, 'total' => count($data)]);
    }

    #[Route('/{id}/pdf', name: 'gestion_entretien_pdf', methods: ['GET'])]
    public function exportPdf(Entretien $entretien, SessionInterface $session, Environment $twig): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $evaluation = $entretien->getEvaluationEntretiens()->first() ?: null;

        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/logo.png';
        $logoBase64 = is_file($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        $html = $twig->render('pdf/entretien_report.html.twig', [
            'entretien' => $entretien,
            'evaluation' => $evaluation,
            'generatedAt' => new \DateTime(),
            'logoBase64' => $logoBase64,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('entretien-%d-%s.pdf', $entretien->getId(), (new \DateTime())->format('Ymd'));

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    #[Route('/{id}', name: 'gestion_entretien_show', methods: ['GET'])]
    public function show(Entretien $entretien, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        return $this->render('gestion_entretien/show.html.twig', [
            'entretien' => $entretien,
        ]);
    }

    #[Route('/{id}/edit', name: 'gestion_entretien_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Entretien $entretien, EntityManagerInterface $entityManager, EntretienNotificationService $notificationService, GoogleCalendarService $calendar, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $form = $this->createForm(EntretienType::class, $entretien);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            try {
                $existingEventId = $entretien->getCalendarEventId();
                if ($existingEventId !== null) {
                    $calendar->updateEvent($existingEventId, $entretien);
                } else {
                    $eventId = $calendar->createEvent($entretien);
                    $entretien->setCalendarEventId($eventId);
                    $entityManager->flush();
                }
                $this->addFlash('success', 'Google Calendar mis à jour.');
            } catch (\Throwable) {
                $this->addFlash('warning', 'Entretien modifié, mais échec de synchronisation Google Calendar.');
            }

            try {
                $sentCount = $notificationService->notifyEntretienUpdated($entretien);
                if ($sentCount > 0) {
                    $this->addFlash('success', sprintf('Notification de mise a jour envoyee a %d destinataire(s).', $sentCount));
                }
            } catch (\Throwable) {
                $this->addFlash('warning', 'Entretien modifie, mais echec de notification email.');
            }

            $this->addFlash('success', 'Entretien modifié avec succès.');

            return $this->redirectToRoute('gestion_entretien_dashboard');
        }

        return $this->render('gestion_entretien/edit.html.twig', [
            'form' => $form->createView(),
            'entretien' => $entretien,
        ]);
    }

    #[Route('/{id}', name: 'gestion_entretien_delete', methods: ['POST'])]
    public function delete(Request $request, Entretien $entretien, EntityManagerInterface $entityManager, GoogleCalendarService $calendar, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        if ($this->isCsrfTokenValid('delete' . $entretien->getId(), $request->getPayload()->getString('_token'))) {
            $calendarEventId = $entretien->getCalendarEventId();
            try {
                $entityManager->remove($entretien);
                $entityManager->flush();
                $this->addFlash('success', 'Entretien supprimé.');
            } catch (ForeignKeyConstraintViolationException) {
                $this->addFlash('error', 'Suppression bloquée par contrainte FK. Activez ON DELETE CASCADE pour evaluation_entretien.');
                return $this->redirectToRoute('gestion_entretien_dashboard');
            }

            if ($calendarEventId !== null) {
                try {
                    $calendar->deleteEvent($calendarEventId);
                } catch (\Throwable) {
                    $this->addFlash('warning', 'Entretien supprimé, mais échec de suppression dans Google Calendar.');
                }
            }
        }

        return $this->redirectToRoute('gestion_entretien_dashboard');
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

    private function resolveStatsFilters(Request $request): array
    {
        $dateDebut = (string) $request->query->get('dateDebut', '');
        $dateFin = (string) $request->query->get('dateFin', '');
        $typeFilter = (string) $request->query->get('type', '');
        $periode = (string) $request->query->get('periode', '');

        $today = new \DateTime();
        if ($periode === '7j') {
            $dateDebut = (clone $today)->modify('-7 days')->format('Y-m-d');
            $dateFin = $today->format('Y-m-d');
        } elseif ($periode === '30j') {
            $dateDebut = (clone $today)->modify('-30 days')->format('Y-m-d');
            $dateFin = $today->format('Y-m-d');
        } elseif ($periode === '3m') {
            $dateDebut = (clone $today)->modify('-3 months')->format('Y-m-d');
            $dateFin = $today->format('Y-m-d');
        } elseif ($periode === '6m') {
            $dateDebut = (clone $today)->modify('-6 months')->format('Y-m-d');
            $dateFin = $today->format('Y-m-d');
        } elseif ($periode === '1an') {
            $dateDebut = (clone $today)->modify('-1 year')->format('Y-m-d');
            $dateFin = $today->format('Y-m-d');
        }

        return [$dateDebut ?: null, $dateFin ?: null, $typeFilter ?: null, $periode];
    }

    private function calculateEntretienStats(array $entretiens): array
    {
        $total = count($entretiens);
        $termines = 0;
        $planifies = 0;
        $autres = 0;
        $nbRH = 0;
        $nbTechnique = 0;
        $parStatut = [];
        $parType = [];
        $parMois = [];

        foreach ($entretiens as $entretien) {
            $statut = $entretien->getStatutEntretien() ?? 'Autre';
            $type = $entretien->getTypeEntretien() ?? 'Autre';
            $lowerStatut = strtolower($statut);

            if (str_contains($lowerStatut, 'termin')) {
                $termines++;
            } elseif (str_contains($lowerStatut, 'planifi')) {
                $planifies++;
            } else {
                $autres++;
            }

            if ($type === 'RH') {
                $nbRH++;
            }

            if ($type === 'TECHNIQUE') {
                $nbTechnique++;
            }

            $parStatut[$statut] = ($parStatut[$statut] ?? 0) + 1;
            $parType[$type] = ($parType[$type] ?? 0) + 1;

            if ($entretien->getDateEntretien()) {
                $mois = $entretien->getDateEntretien()->format('M Y');
                $parMois[$mois] = ($parMois[$mois] ?? 0) + 1;
            }
        }

        return [
            'total' => $total,
            'termines' => $termines,
            'planifies' => $planifies,
            'autres' => $autres,
            'nbRH' => $nbRH,
            'nbTechnique' => $nbTechnique,
            'parStatutLabels' => array_keys($parStatut),
            'parStatutData' => array_values($parStatut),
            'parTypeLabels' => array_keys($parType),
            'parTypeData' => array_values($parType),
            'parMoisLabels' => array_keys($parMois),
            'parMoisData' => array_values($parMois),
        ];
    }

    private function calculateEvaluationStats(array $evaluations): array
    {
        $totalEvals = count($evaluations);
        $nbAcceptes = 0;
        $nbRefuses = 0;
        $nbEnAttente = 0;
        $scoreMoyen = 0;
        $noteMoyenne = 0;
        $evalStats = [0, 0, 0, 0, 0];

        foreach ($evaluations as $evaluation) {
            $decision = strtolower($evaluation->getDecision() ?? '');
            if (str_contains($decision, 'accept')) {
                $nbAcceptes++;
            } elseif (str_contains($decision, 'refus')) {
                $nbRefuses++;
            } else {
                $nbEnAttente++;
            }

            $scoreMoyen += $evaluation->getScoreTest() ?? 0;
            $noteMoyenne += $evaluation->getNoteEntretien() ?? 0;
            $evalStats[0] += $evaluation->getCompetencesTechniques() ?? 0;
            $evalStats[1] += $evaluation->getCompetencesComportementales() ?? 0;
            $evalStats[2] += $evaluation->getCommunication() ?? 0;
            $evalStats[3] += $evaluation->getMotivation() ?? 0;
            $evalStats[4] += $evaluation->getExperience() ?? 0;
        }

        if ($totalEvals > 0) {
            $scoreMoyen = round($scoreMoyen / $totalEvals, 1);
            $noteMoyenne = round($noteMoyenne / $totalEvals, 1);
            $evalStats = array_map(static fn (float|int $value): float => round($value / $totalEvals, 1), $evalStats);
        }

        return [
            'totalEvals' => $totalEvals,
            'nbAcceptes' => $nbAcceptes,
            'nbRefuses' => $nbRefuses,
            'nbEnAttente' => $nbEnAttente,
            'scoreMoyen' => $scoreMoyen,
            'noteMoyenne' => $noteMoyenne,
            'evalStats' => $evalStats,
            'tauxReussite' => $totalEvals > 0 ? round(($nbAcceptes / $totalEvals) * 100, 1) : 0,
        ];
    }
}

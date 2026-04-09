<?php

namespace App\Controller;

use App\Entity\Entretien;
use App\Form\EntretienType;
use App\Repository\EntretienRepository;
use App\Repository\EvaluationEntretienRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/gestion-entretien')]
class EntretienController extends AbstractController
{
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
    public function new(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
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
            $this->addFlash('success', 'Entretien ajouté avec succès.');

            return $this->redirectToRoute('gestion_entretien_dashboard');
        }

        return $this->render('gestion_entretien/new.html.twig', [
            'form' => $form->createView(),
            'entretien' => $entretien,
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
    public function edit(Request $request, Entretien $entretien, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $form = $this->createForm(EntretienType::class, $entretien);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Entretien modifié avec succès.');

            return $this->redirectToRoute('gestion_entretien_dashboard');
        }

        return $this->render('gestion_entretien/edit.html.twig', [
            'form' => $form->createView(),
            'entretien' => $entretien,
        ]);
    }

    #[Route('/{id}', name: 'gestion_entretien_delete', methods: ['POST'])]
    public function delete(Request $request, Entretien $entretien, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        if ($this->isCsrfTokenValid('delete' . $entretien->getId(), $request->getPayload()->getString('_token'))) {
            try {
                $entityManager->remove($entretien);
                $entityManager->flush();
                $this->addFlash('success', 'Entretien supprimé.');
            } catch (ForeignKeyConstraintViolationException) {
                $this->addFlash('error', 'Suppression bloquée par contrainte FK. Activez ON DELETE CASCADE pour evaluation_entretien.');
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

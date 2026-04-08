<?php

namespace App\Controller;

use App\Repository\EntretienRepository;
use App\Repository\EvaluationEntretienRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/candidat')]
class FrontController extends AbstractController
{
    #[Route('/', name: 'app_front_dashboard', methods: ['GET'])]
    public function dashboard(EntretienRepository $entretienRepository, EvaluationEntretienRepository $evaluationRepository, SessionInterface $session): Response
    {
        $userId = $this->requireClient($session);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $entretiens = $entretienRepository->findForUser($userId);
        $evaluations = $evaluationRepository->findForUser($userId);

        $totalEntretiens = count($entretiens);
        $termines = 0;
        $planifies = 0;

        foreach ($entretiens as $entretien) {
            $statut = strtolower($entretien->getStatutEntretien() ?? '');
            if (str_contains($statut, 'termin')) {
                $termines++;
            } elseif (str_contains($statut, 'planifi')) {
                $planifies++;
            }
        }

        $dernierEntretiens = array_slice(array_reverse($entretiens), 0, 5);
        $totalEvals = count($evaluations);
        $scoreMoyen = 0;
        $noteMoyenne = 0;
        $evalStats = [0, 0, 0, 0, 0];
        $derniereDecision = null;

        if ($totalEvals > 0) {
            foreach ($evaluations as $evaluation) {
                $scoreMoyen += $evaluation->getScoreTest() ?? 0;
                $noteMoyenne += $evaluation->getNoteEntretien() ?? 0;
                $evalStats[0] += $evaluation->getCompetencesTechniques() ?? 0;
                $evalStats[1] += $evaluation->getCompetencesComportementales() ?? 0;
                $evalStats[2] += $evaluation->getCommunication() ?? 0;
                $evalStats[3] += $evaluation->getMotivation() ?? 0;
                $evalStats[4] += $evaluation->getExperience() ?? 0;
            }

            $scoreMoyen = round($scoreMoyen / $totalEvals, 1);
            $noteMoyenne = round($noteMoyenne / $totalEvals, 1);
            $evalStats = array_map(static fn (float|int $value): float => round($value / $totalEvals, 1), $evalStats);
            $derniereDecision = end($evaluations)?->getDecision();
        }

        return $this->render('front/dashboard.html.twig', [
            'totalEntretiens' => $totalEntretiens,
            'termines' => $termines,
            'planifies' => $planifies,
            'totalEvals' => $totalEvals,
            'dernierEntretiens' => $dernierEntretiens,
            'scoreMoyen' => $scoreMoyen,
            'noteMoyenne' => round($noteMoyenne),
            'evalStats' => $evalStats,
            'derniereDecision' => $derniereDecision,
            'userName' => (string) $session->get('user_name', 'Client'),
        ]);
    }

    #[Route('/entretiens', name: 'app_front_entretiens', methods: ['GET'])]
    public function entretiens(Request $request, EntretienRepository $repo, SessionInterface $session): Response
    {
        $userId = $this->requireClient($session);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $search = (string) $request->query->get('search', '');
        $type = (string) $request->query->get('type', '');
        $statut = (string) $request->query->get('statut', '');

        return $this->render('front/entretiens.html.twig', [
            'entretiens' => $repo->findForUser($userId, $search ?: null, $type ?: null, $statut ?: null, 'e.dateEntretien', 'DESC'),
            'search' => $search,
            'type' => $type,
            'statut' => $statut,
            'userName' => (string) $session->get('user_name', 'Client'),
        ]);
    }

    #[Route('/entretiens/{id}', name: 'app_front_entretien_show', methods: ['GET'])]
    public function entretienShow(int $id, EntretienRepository $repo, SessionInterface $session): Response
    {
        $userId = $this->requireClient($session);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $entretien = $repo->createQueryBuilder('e')
            ->andWhere('e.id = :id')
            ->andWhere('e.idUtilisateur = :userId')
            ->setParameter('id', $id)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$entretien) {
            throw $this->createNotFoundException('Entretien introuvable.');
        }

        return $this->render('front/entretien_show.html.twig', [
            'entretien' => $entretien,
            'userName' => (string) $session->get('user_name', 'Client'),
        ]);
    }

    #[Route('/evaluations', name: 'app_front_evaluations', methods: ['GET'])]
    public function evaluations(EvaluationEntretienRepository $repo, SessionInterface $session): Response
    {
        $userId = $this->requireClient($session);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        return $this->render('front/evaluations.html.twig', [
            'evaluations' => $repo->findForUser($userId),
            'userName' => (string) $session->get('user_name', 'Client'),
        ]);
    }

    private function requireClient(SessionInterface $session): int|RedirectResponse
    {
        $userId = (int) $session->get('user_id', 0);
        $userRole = (string) $session->get('user_role', '');

        if ($userId <= 0 || $userRole !== 'CLIENT') {
            $this->addFlash('error', 'Veuillez vous connecter en client.');
            return $this->redirectToRoute('app_signin');
        }

        return $userId;
    }
}

<?php

namespace App\Controller;

use App\Repository\EntretienRepository;
use App\Repository\EvaluationEntretienRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Entretien;

#[Route('/candidat')]
class FrontController extends AbstractController
{
    /**
     * Dashboard candidat
     */
    #[Route('/', name: 'app_front_dashboard', methods: ['GET'])]
    public function dashboard(
        EntretienRepository $entretienRepo,
        EvaluationEntretienRepository $evalRepo
    ): Response {
        // Récupère tous les entretiens (à filtrer par user connecté quand auth dispo)
        $entretiens  = $entretienRepo->findAll();
        $evaluations = $evalRepo->findAll();

        // KPIs
        $totalEntretiens = count($entretiens);
        $termines  = 0;
        $planifies = 0;
        foreach ($entretiens as $e) {
            $s = strtolower($e->getStatutEntretien() ?? '');
            if (str_contains($s, 'termin'))      $termines++;
            elseif (str_contains($s, 'planifi')) $planifies++;
        }

        // Derniers 5 entretiens
        $dernierEntretiens = array_slice(
            array_reverse($entretiens),
            0, 5
        );

        // Stats évaluations
        $totalEvals    = count($evaluations);
        $scoreMoyen    = 0;
        $noteMoyenne   = 0;
        $evalStats     = [0, 0, 0, 0, 0];
        $derniereDecision = null;

        if ($totalEvals > 0) {
            foreach ($evaluations as $ev) {
                $scoreMoyen  += $ev->getScoreTest()     ?? 0;
                $noteMoyenne += $ev->getNoteEntretien() ?? 0;
                $evalStats[0] += $ev->getCompetencesTechniques()       ?? 0;
                $evalStats[1] += $ev->getCompetencesComportementales() ?? 0;
                $evalStats[2] += $ev->getCommunication() ?? 0;
                $evalStats[3] += $ev->getMotivation()    ?? 0;
                $evalStats[4] += $ev->getExperience()    ?? 0;
            }
            $scoreMoyen    = round($scoreMoyen  / $totalEvals, 1);
            $noteMoyenne   = round($noteMoyenne / $totalEvals, 1);
            $evalStats     = array_map(fn($v) => round($v / $totalEvals, 1), $evalStats);
            $derniereDecision = end($evaluations)->getDecision();
        }

        return $this->render('front/dashboard.html.twig', [
            'totalEntretiens'  => $totalEntretiens,
            'termines'         => $termines,
            'planifies'        => $planifies,
            'totalEvals'       => $totalEvals,
            'dernierEntretiens'=> $dernierEntretiens,
            'scoreMoyen'       => $scoreMoyen,
            'noteMoyenne'      => round($noteMoyenne),
            'evalStats'        => $evalStats,
            'derniereDecision' => $derniereDecision,
        ]);
    }

    /**
     * Liste des entretiens du candidat
     */
    #[Route('/entretiens', name: 'app_front_entretiens', methods: ['GET'])]
    public function entretiens(Request $request, EntretienRepository $repo): Response
    {
        $search  = $request->query->get('search');
        $type    = $request->query->get('type');
        $statut  = $request->query->get('statut');

        $entretiens = $repo->findWithFilters($search, $type, $statut, 'e.dateEntretien', 'DESC');

        return $this->render('front/entretiens.html.twig', [
            'entretiens' => $entretiens,
            'search'     => $search,
            'type'       => $type,
            'statut'     => $statut,
        ]);
    }

    /**
     * Détail d'un entretien
     */
    #[Route('/entretiens/{id}', name: 'app_front_entretien_show', methods: ['GET'])]
    public function entretienShow(Entretien $entretien): Response
    {
        return $this->render('front/entretien_show.html.twig', [
            'entretien' => $entretien,
        ]);
    }

    /**
     * Mes évaluations
     */
    #[Route('/evaluations', name: 'app_front_evaluations', methods: ['GET'])]
    public function evaluations(EvaluationEntretienRepository $repo): Response
    {
        return $this->render('front/evaluations.html.twig', [
            'evaluations' => $repo->findAll(),
        ]);
    }
}

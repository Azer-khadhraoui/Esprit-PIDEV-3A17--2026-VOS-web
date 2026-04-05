<?php

namespace App\Controller;

use App\Entity\Entretien;
use App\Form\EntretienType;
use App\Repository\EntretienRepository;
use App\Repository\EvaluationEntretienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/entretien')]
class EntretienController extends AbstractController
{
    #[Route('/', name: 'app_entretien_index', methods: ['GET'])]
    public function index(Request $request, EntretienRepository $repo): Response
    {
        $search  = $request->query->get('search');
        $type    = $request->query->get('type');
        $statut  = $request->query->get('statut');
        $sortBy  = $request->query->get('sortBy', 'e.dateEntretien');
        $sortDir = $request->query->get('sortDir', 'DESC');

        return $this->render('entretien/index.html.twig', [
            'entretiens' => $repo->findWithFilters($search, $type, $statut, $sortBy, $sortDir),
            'search'     => $search,
            'type'       => $type,
            'statut'     => $statut,
            'sortBy'     => $sortBy,
            'sortDir'    => $sortDir,
        ]);
    }

    #[Route('/stats', name: 'app_entretien_stats', methods: ['GET'])]
    public function stats(EntretienRepository $entretienRepository, EvaluationEntretienRepository $evalRepository): Response
    {
        $entretiens  = $entretienRepository->findAll();
        $evaluations = $evalRepository->findAll();

        $total = count($entretiens);
        $termines = $planifies = $autres = 0;
        $parStatut = $parType = [];

        foreach ($entretiens as $e) {
            $statut = $e->getStatutEntretien() ?? 'Autre';
            $type   = $e->getTypeEntretien()   ?? 'Autre';
            $sl     = strtolower($statut);

            if (str_contains($sl, 'termin'))      $termines++;
            elseif (str_contains($sl, 'planifi')) $planifies++;
            else                                   $autres++;

            $parStatut[$statut] = ($parStatut[$statut] ?? 0) + 1;
            $parType[$type]     = ($parType[$type]     ?? 0) + 1;
        }

        $totalEvals = count($evaluations);
        $scoreMoyen = $noteMoyenne = 0;
        $evalStats  = [0, 0, 0, 0, 0];

        if ($totalEvals > 0) {
            foreach ($evaluations as $ev) {
                $scoreMoyen  += $ev->getScoreTest()      ?? 0;
                $noteMoyenne += $ev->getNoteEntretien()  ?? 0;
                $evalStats[0] += $ev->getCompetencesTechniques()       ?? 0;
                $evalStats[1] += $ev->getCompetencesComportementales() ?? 0;
                $evalStats[2] += $ev->getCommunication() ?? 0;
                $evalStats[3] += $ev->getMotivation()    ?? 0;
                $evalStats[4] += $ev->getExperience()    ?? 0;
            }
            $scoreMoyen  = round($scoreMoyen  / $totalEvals, 1);
            $noteMoyenne = round($noteMoyenne / $totalEvals, 1);
            $evalStats   = array_map(fn($v) => round($v / $totalEvals, 1), $evalStats);
        }

        return $this->render('entretien/stats.html.twig', [
            'total'           => $total,
            'termines'        => $termines,
            'planifies'       => $planifies,
            'autres'          => $autres,
            'totalEvals'      => $totalEvals,
            'scoreMoyen'      => $scoreMoyen,
            'noteMoyenne'     => $noteMoyenne,
            'parStatutLabels' => array_keys($parStatut),
            'parStatutData'   => array_values($parStatut),
            'parTypeLabels'   => array_keys($parType),
            'parTypeData'     => array_values($parType),
            'evalStats'       => $evalStats,
        ]);
    }

    #[Route('/new', name: 'app_entretien_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $entretien = new Entretien();
        $form = $this->createForm(EntretienType::class, $entretien);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entretien);
            $em->flush();
            $this->addFlash('success', 'Entretien ajouté avec succès !');
            return $this->redirectToRoute('app_entretien_index');
        }

        return $this->render('entretien/new.html.twig', [
            'entretien' => $entretien,
            'form'      => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_entretien_show', methods: ['GET'])]
    public function show(Entretien $entretien): Response
    {
        return $this->render('entretien/show.html.twig', ['entretien' => $entretien]);
    }

    #[Route('/{id}/edit', name: 'app_entretien_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Entretien $entretien, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EntretienType::class, $entretien);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Entretien modifié avec succès !');
            return $this->redirectToRoute('app_entretien_index');
        }

        return $this->render('entretien/edit.html.twig', [
            'entretien' => $entretien,
            'form'      => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_entretien_delete', methods: ['POST'])]
    public function delete(Request $request, Entretien $entretien, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $entretien->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($entretien);
            $em->flush();
            $this->addFlash('success', 'Entretien supprimé.');
        }
        return $this->redirectToRoute('app_entretien_index');
    }
}

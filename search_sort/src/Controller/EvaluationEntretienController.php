<?php

namespace App\Controller;

use App\Entity\EvaluationEntretien;
use App\Form\EvaluationEntretienType;
use App\Repository\EvaluationEntretienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/evaluation/entretien')]
class EvaluationEntretienController extends AbstractController
{
    #[Route('/', name: 'app_evaluation_entretien_index', methods: ['GET'])]
    public function index(Request $request, EvaluationEntretienRepository $repo): Response
    {
        $search   = $request->query->get('search');
        $decision = $request->query->get('decision');
        $sortBy   = $request->query->get('sortBy', 'ev.scoreTest');
        $sortDir  = $request->query->get('sortDir', 'DESC');

        return $this->render('evaluation_entretien/index.html.twig', [
            'evaluation_entretiens' => $repo->findWithFilters($search, $decision, $sortBy, $sortDir),
            'search'                => $search,
            'decision'              => $decision,
            'sortBy'                => $sortBy,
            'sortDir'               => $sortDir,
        ]);
    }

    #[Route('/new', name: 'app_evaluation_entretien_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $evaluation = new EvaluationEntretien();
        $form = $this->createForm(EvaluationEntretienType::class, $evaluation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($evaluation);
            $em->flush();
            $this->addFlash('success', 'Évaluation ajoutée avec succès !');
            return $this->redirectToRoute('app_evaluation_entretien_index');
        }

        return $this->render('evaluation_entretien/new.html.twig', [
            'evaluation_entretien' => $evaluation,
            'form'                 => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_evaluation_entretien_show', methods: ['GET'])]
    public function show(EvaluationEntretien $evaluationEntretien): Response
    {
        return $this->render('evaluation_entretien/show.html.twig', [
            'evaluation_entretien' => $evaluationEntretien,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evaluation_entretien_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EvaluationEntretien $evaluationEntretien, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EvaluationEntretienType::class, $evaluationEntretien);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Évaluation modifiée avec succès !');
            return $this->redirectToRoute('app_evaluation_entretien_index');
        }

        return $this->render('evaluation_entretien/edit.html.twig', [
            'evaluation_entretien' => $evaluationEntretien,
            'form'                 => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_evaluation_entretien_delete', methods: ['POST'])]
    public function delete(Request $request, EvaluationEntretien $evaluationEntretien, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $evaluationEntretien->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($evaluationEntretien);
            $em->flush();
            $this->addFlash('success', 'Évaluation supprimée.');
        }
        return $this->redirectToRoute('app_evaluation_entretien_index');
    }
}

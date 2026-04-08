<?php

namespace App\Controller;

use App\Entity\EvaluationEntretien;
use App\Form\EvaluationEntretienType;
use App\Repository\EntretienRepository;
use App\Repository\EvaluationEntretienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function new(Request $request, EntityManagerInterface $entityManager, EntretienRepository $entretienRepository, SessionInterface $session): Response
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
            $this->addFlash('success', 'Évaluation ajoutée avec succès.');

            return $this->redirectToRoute('app_evaluation_entretien_index');
        }

        return $this->render('evaluation_entretien/new.html.twig', [
            'form' => $form->createView(),
            'evaluation_entretien' => $evaluation,
        ]);
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
    public function edit(Request $request, EvaluationEntretien $evaluationEntretien, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $form = $this->createForm(EvaluationEntretienType::class, $evaluationEntretien);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
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

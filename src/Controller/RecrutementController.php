<?php

namespace App\Controller;

use App\Entity\Recrutement;
use App\Form\RecrutementType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RecrutementController extends AbstractController
{
    #[Route('/admin/recrutements', name: 'recrutement_index')]
    public function index(ManagerRegistry $doctrine): Response
    {
        $recrutements = $doctrine->getRepository(Recrutement::class)->findAll();

        return $this->render('recrutement/index.html.twig', [
            'recrutements' => $recrutements,
        ]);
    }

    #[Route('/admin/recrutements/new', name: 'recrutement_new')]
    public function new(Request $request, ManagerRegistry $doctrine): Response
    {
        $recrutement = new Recrutement();
        $form = $this->createForm(RecrutementType::class, $recrutement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();
            $entityManager->persist($recrutement);
            $entityManager->flush();

            $this->addFlash('success', 'Recrutement ajouté avec succès.');

            return $this->redirectToRoute('recrutement_index');
        }

        return $this->render('recrutement/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/recrutements/{id}/edit', name: 'recrutement_edit')]
    public function edit(Request $request, ManagerRegistry $doctrine, Recrutement $recrutement): Response
    {
        $form = $this->createForm(RecrutementType::class, $recrutement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $doctrine->getManager()->flush();
            $this->addFlash('success', 'Recrutement mis à jour avec succès.');

            return $this->redirectToRoute('recrutement_index');
        }

        return $this->render('recrutement/edit.html.twig', [
            'form' => $form->createView(),
            'recrutement' => $recrutement,
        ]);
    }

    #[Route('/admin/recrutements/{id}/delete', name: 'recrutement_delete', methods: ['POST'])]
    public function delete(Request $request, ManagerRegistry $doctrine, Recrutement $recrutement): Response
    {
        if ($this->isCsrfTokenValid('delete_recrutement_' . $recrutement->getId(), $request->request->get('_token'))) {
            $entityManager = $doctrine->getManager();
            $entityManager->remove($recrutement);
            $entityManager->flush();
            $this->addFlash('success', 'Recrutement supprimé avec succès.');
        }

        return $this->redirectToRoute('recrutement_index');
    }
}

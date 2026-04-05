<?php

namespace App\Controller;

use App\Entity\PreferenceCandidature;
use App\Form\PreferenceCandidatureType;
use App\Repository\PreferenceCandidatureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/preference/candidature')]
final class PreferenceCandidatureController extends AbstractController
{
    #[Route(name: 'app_preference_candidature_index', methods: ['GET'])]
    public function index(PreferenceCandidatureRepository $preferenceCandidatureRepository): Response
    {
        return $this->render('preference_candidature/index.html.twig', [
            'preference_candidatures' => $preferenceCandidatureRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_preference_candidature_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $preferenceCandidature = new PreferenceCandidature();
        $form = $this->createForm(PreferenceCandidatureType::class, $preferenceCandidature);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($preferenceCandidature);
            $entityManager->flush();

            return $this->redirectToRoute('app_preference_candidature_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('preference_candidature/new.html.twig', [
            'preference_candidature' => $preferenceCandidature,
            'form' => $form,
        ]);
    }

    #[Route('/{id_preference}', name: 'app_preference_candidature_show', methods: ['GET'])]
    public function show(PreferenceCandidature $preferenceCandidature): Response
    {
        return $this->render('preference_candidature/show.html.twig', [
            'preference_candidature' => $preferenceCandidature,
        ]);
    }

    #[Route('/{id_preference}/edit', name: 'app_preference_candidature_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PreferenceCandidature $preferenceCandidature, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PreferenceCandidatureType::class, $preferenceCandidature);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_preference_candidature_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('preference_candidature/edit.html.twig', [
            'preference_candidature' => $preferenceCandidature,
            'form' => $form,
        ]);
    }

    #[Route('/{id_preference}', name: 'app_preference_candidature_delete', methods: ['POST'])]
    public function delete(Request $request, PreferenceCandidature $preferenceCandidature, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$preferenceCandidature->getIdPreference(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($preferenceCandidature);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_preference_candidature_index', [], Response::HTTP_SEE_OTHER);
    }
}

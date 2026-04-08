<?php

namespace App\Controller;

use App\Entity\ContratEmbauche;
use App\Entity\Recrutement;
use App\Form\ContratEmbaucheType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContratEmbaucheController extends AbstractController
{
    #[Route('/admin/contrats-embauche', name: 'contrat_embauche_index')]
    public function index(ManagerRegistry $doctrine): Response
    {
        $contrats = $doctrine->getRepository(ContratEmbauche::class)->findAll();

        return $this->render('contrat_embauche/index.html.twig', ['contrats' => $contrats]);
    }

    #[Route('/admin/contrats-embauche/new', name: 'contrat_embauche_new')]
    public function new(Request $request, ManagerRegistry $doctrine): Response
    {
        $recrutementRepo = $doctrine->getRepository(Recrutement::class);
        $acceptedRecrutements = $recrutementRepo->findBy(['decisionFinale' => 'Accepté']);
        $choices = [];
        foreach ($acceptedRecrutements as $rec) {
            $choices['Recrutement ' . $rec->getId()] = $rec->getId();
        }

        $contrat = new ContratEmbauche();
        $form = $this->createForm(ContratEmbaucheType::class, $contrat, ['recrutement_choices' => $choices]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $recrutement = $doctrine->getRepository(Recrutement::class)->find($contrat->getIdRecrutement());
            if (!$recrutement) {
                $form->get('idRecrutement')->addError(new FormError('Le recrutement avec cet ID n\'existe pas.'));
                return $this->render('contrat_embauche/new.html.twig', ['form' => $form->createView()]);
            }
            // Calculate status based on dates
            $today = new \DateTime();
            if ($contrat->getDateDebut() && $contrat->getDateFin()) {
                if ($today >= $contrat->getDateDebut() && $today <= $contrat->getDateFin()) {
                    $status = 'Actif';
                } elseif ($today < $contrat->getDateDebut()) {
                    $status = 'En attente';
                } else {
                    $status = 'Terminé';
                }
                $contrat->setStatus($status);
            }
            $contrat->setPeriode($contrat->getPeriode());
            $em = $doctrine->getManager();
            $em->persist($contrat);
            $em->flush();

            $this->addFlash('success', 'Contrat créé avec succès.');

            return $this->redirectToRoute('contrat_embauche_index');
        }

        return $this->render('contrat_embauche/new.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/admin/contrats-embauche/{id}/edit', name: 'contrat_embauche_edit')]
    public function edit(Request $request, ManagerRegistry $doctrine, ContratEmbauche $contrat): Response
    {
        $recrutementRepo = $doctrine->getRepository(Recrutement::class);
        $acceptedRecrutements = $recrutementRepo->findBy(['decisionFinale' => 'Accepté']);
        $choices = [];
        foreach ($acceptedRecrutements as $rec) {
            $choices['Recrutement ' . $rec->getId()] = $rec->getId();
        }

        $form = $this->createForm(ContratEmbaucheType::class, $contrat, ['recrutement_choices' => $choices]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $recrutement = $doctrine->getRepository(Recrutement::class)->find($contrat->getIdRecrutement());
            if (!$recrutement) {
                $form->get('idRecrutement')->addError(new FormError('Le recrutement avec cet ID n\'existe pas.'));
                return $this->render('contrat_embauche/edit.html.twig', ['form' => $form->createView(), 'contrat' => $contrat]);
            }
            // Calculate status based on dates
            $today = new \DateTime();
            if ($contrat->getDateDebut() && $contrat->getDateFin()) {
                if ($today >= $contrat->getDateDebut() && $today <= $contrat->getDateFin()) {
                    $status = 'Actif';
                } elseif ($today < $contrat->getDateDebut()) {
                    $status = 'En attente';
                } else {
                    $status = 'Terminé';
                }
                $contrat->setStatus($status);
            }
            $contrat->setPeriode($contrat->getPeriode());
            $doctrine->getManager()->flush();
            $this->addFlash('success', 'Contrat mis à jour avec succès.');

            return $this->redirectToRoute('contrat_embauche_index');
        }

        return $this->render('contrat_embauche/edit.html.twig', ['form' => $form->createView(), 'contrat' => $contrat]);
    }

    #[Route('/admin/contrats-embauche/{id}/delete', name: 'contrat_embauche_delete', methods: ['POST'])]
    public function delete(Request $request, ManagerRegistry $doctrine, ContratEmbauche $contrat): Response
    {
        if ($this->isCsrfTokenValid('delete_contrat_' . $contrat->getId(), $request->request->get('_token'))) {
            $em = $doctrine->getManager();
            $em->remove($contrat);
            $em->flush();
            $this->addFlash('success', 'Contrat supprimé avec succès.');
        }

        return $this->redirectToRoute('contrat_embauche_index');
    }
}

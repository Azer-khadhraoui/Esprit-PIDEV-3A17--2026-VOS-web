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
    public function index(Request $request, ManagerRegistry $doctrine): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $status = trim((string) $request->query->get('status', ''));
        $typeContrat = trim((string) $request->query->get('typeContrat', ''));
        $sortBy = (string) $request->query->get('sortBy', 'dateDebut');
        $sortOrder = strtoupper((string) $request->query->get('sortOrder', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $sortMap = [
            'id' => 'c.id',
            'typeContrat' => 'c.typeContrat',
            'dateDebut' => 'c.dateDebut',
            'dateFin' => 'c.dateFin',
            'salaire' => 'c.salaire',
            'status' => 'c.status',
        ];

        $qb = $doctrine->getRepository(ContratEmbauche::class)->createQueryBuilder('c');

        if ($search !== '') {
            $searchExpr = $qb->expr()->orX(
                'LOWER(c.typeContrat) LIKE :search',
                'LOWER(c.status) LIKE :search',
                'LOWER(c.volumeHoraire) LIKE :search',
                'LOWER(c.avantages) LIKE :search'
            );
            $qb->setParameter('search', '%' . strtolower($search) . '%');

            if (ctype_digit($search)) {
                $searchExpr->add('c.id = :searchNumber');
                $qb->setParameter('searchNumber', (int) $search);
            }

            $qb->andWhere($searchExpr);
        }

        if ($status !== '' && $status !== 'Tous') {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        if ($typeContrat !== '' && $typeContrat !== 'Tous') {
            $qb->andWhere('c.typeContrat = :typeContrat')
                ->setParameter('typeContrat', $typeContrat);
        }

        $qb->orderBy($sortMap[$sortBy] ?? $sortMap['dateDebut'], $sortOrder);
        $contrats = $qb->getQuery()->getResult();

        $contratStats = [
            'total' => count($contrats),
            'actifs' => 0,
            'termines' => 0,
        ];

        foreach ($contrats as $contrat) {
            $statusNormalized = strtolower(trim((string) ($contrat->getStatus() ?? '')));
            if ($statusNormalized === 'actif') {
                ++$contratStats['actifs'];
            }
            if (in_array($statusNormalized, ['termine', 'terminé'], true)) {
                ++$contratStats['termines'];
            }
        }

        return $this->render('contrat_embauche/index.html.twig', [
            'contrats' => $contrats,
            'stats' => $contratStats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'typeContrat' => $typeContrat,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
            ],
            'statusOptions' => ['Actif', 'En attente', 'Termine'],
            'typeContratOptions' => ['CDI', 'CDD', 'Stage', 'Freelance', 'Alternance'],
        ]);
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
                $form->get('idRecrutement')->addError(new FormError("Le recrutement avec cet ID n'existe pas."));
                return $this->render('contrat_embauche/new.html.twig', ['form' => $form->createView()]);
            }

            $today = new \DateTime();
            if ($contrat->getDateDebut() && $contrat->getDateFin()) {
                if ($today >= $contrat->getDateDebut() && $today <= $contrat->getDateFin()) {
                    $status = 'Actif';
                } elseif ($today < $contrat->getDateDebut()) {
                    $status = 'En attente';
                } else {
                    $status = 'Termine';
                }
                $contrat->setStatus($status);
            }

            $contrat->setPeriode($contrat->getPeriode());
            $em = $doctrine->getManager();
            $em->persist($contrat);
            $em->flush();

            $this->addFlash('success', 'Contrat cree avec succes.');

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
                $form->get('idRecrutement')->addError(new FormError("Le recrutement avec cet ID n'existe pas."));
                return $this->render('contrat_embauche/edit.html.twig', ['form' => $form->createView(), 'contrat' => $contrat]);
            }

            $today = new \DateTime();
            if ($contrat->getDateDebut() && $contrat->getDateFin()) {
                if ($today >= $contrat->getDateDebut() && $today <= $contrat->getDateFin()) {
                    $status = 'Actif';
                } elseif ($today < $contrat->getDateDebut()) {
                    $status = 'En attente';
                } else {
                    $status = 'Termine';
                }
                $contrat->setStatus($status);
            }

            $contrat->setPeriode($contrat->getPeriode());
            $doctrine->getManager()->flush();
            $this->addFlash('success', 'Contrat mis a jour avec succes.');

            return $this->redirectToRoute('contrat_embauche_index');
        }

        return $this->render('contrat_embauche/edit.html.twig', ['form' => $form->createView(), 'contrat' => $contrat]);
    }

    #[Route('/admin/contrats-embauche/{id}/delete', name: 'contrat_embauche_delete', methods: ['POST'])]
    public function delete(Request $request, ManagerRegistry $doctrine, ContratEmbauche $contrat): Response
    {
        if ($this->isCsrfTokenValid('delete_contrat_' . $contrat->getId(), (string) $request->request->get('_token'))) {
            $em = $doctrine->getManager();
            $em->remove($contrat);
            $em->flush();
            $this->addFlash('success', 'Contrat supprime avec succes.');
        }

        return $this->redirectToRoute('contrat_embauche_index');
    }
}

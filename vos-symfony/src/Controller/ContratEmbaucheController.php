<?php

namespace App\Controller;

use App\Entity\ContratEmbauche;
use App\Entity\Recrutement;
use App\Form\ContratEmbaucheType;
use App\Repository\ContratEmbaucheRepository;
use App\Service\ContractReminderAiService;
use App\Service\RecrutementNotificationService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContratEmbaucheController extends AbstractController
{
    #[Route('/api/contrats-embauche/reminders', name: 'contrat_embauche_reminders_api', methods: ['GET'])]
    public function reminders(
        Request $request,
        ContratEmbaucheRepository $contratRepository,
        ContractReminderAiService $contractReminderAiService
    ): JsonResponse
    {
        $today = new \DateTimeImmutable('today');
        $maxDays = max(0, $request->query->getInt('maxDays', 30));
        $endDate = $today->modify('+' . $maxDays . ' days');
        $includeAiMessage = $request->query->getBoolean('includeAiMessage', true);

        $contracts = $contratRepository->findEndingBetween($today, $endDate);
        $matchingContracts = [];

        foreach ($contracts as $contract) {
            $dateFin = $contract->getDateFin();
            if ($dateFin === null) {
                continue;
            }

            $daysRemaining = (int) $today->diff(\DateTimeImmutable::createFromInterface($dateFin))->format('%r%a');
            if ($daysRemaining < 0 || $daysRemaining > $maxDays) {
                continue;
            }

            $matchingContracts[] = [
                'id' => $contract->getId(),
                'typeContrat' => $contract->getTypeContrat(),
                'dateDebut' => $contract->getDateDebut()?->format('Y-m-d'),
                'dateFin' => $dateFin->format('Y-m-d'),
                'daysRemaining' => $daysRemaining,
                'status' => $contract->getStatus(),
                'salaire' => $contract->getSalaire(),
                'volumeHoraire' => $contract->getVolumeHoraire(),
                'periode' => $contract->getPeriode(),
                'idRecrutement' => $contract->getIdRecrutement(),
                'aiReminderMessage' => $includeAiMessage ? $contractReminderAiService->generateReminderMessage($contract, $daysRemaining) : null,
            ];
        }

        usort($matchingContracts, static function (array $a, array $b): int {
            return ($a['daysRemaining'] <=> $b['daysRemaining']) ?: strcmp((string) $a['dateFin'], (string) $b['dateFin']);
        });

        return new JsonResponse([
            'ok' => true,
            'generatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'maxDays' => $maxDays,
            'ai' => [
                'enabled' => $includeAiMessage,
                'provider' => 'Groq',
                'configured' => $contractReminderAiService->isConfigured(),
            ],
            'total' => count($matchingContracts),
            'contracts' => $matchingContracts,
        ]);
    }

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
    public function new(Request $request, ManagerRegistry $doctrine, RecrutementNotificationService $notifier): Response
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

            $this->applyContratStatus($contrat);
            $contrat->setPeriode($contrat->getPeriode());
            $em = $doctrine->getManager();
            $em->persist($contrat);
            $em->flush();

            try {
                $notifier->notifyContratCreated($contrat);
            } catch (\Throwable) {
                $this->addFlash('warning', 'Contrat créé, mais l\'envoi de la notification a échoué.');
            }

            $this->addFlash('success', 'Contrat cree avec succes.');

            return $this->redirectToRoute('contrat_embauche_index');
        }

        return $this->render('contrat_embauche/new.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/admin/contrats-embauche/{id}/edit', name: 'contrat_embauche_edit')]
    public function edit(Request $request, ManagerRegistry $doctrine, ContratEmbauche $contrat, RecrutementNotificationService $notifier): Response
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

            $this->applyContratStatus($contrat);
            $contrat->setPeriode($contrat->getPeriode());
            $doctrine->getManager()->flush();

            try {
                $notifier->notifyContratUpdated($contrat);
            } catch (\Throwable) {
                $this->addFlash('warning', 'Contrat mis à jour, mais l\'envoi de la notification a échoué.');
            }

            $this->addFlash('success', 'Contrat mis a jour avec succes.');

            return $this->redirectToRoute('contrat_embauche_index');
        }

        return $this->render('contrat_embauche/edit.html.twig', ['form' => $form->createView(), 'contrat' => $contrat]);
    }

    #[Route('/admin/contrats-embauche/{id}/send-reminder', name: 'contrat_embauche_send_reminder', methods: ['POST'])]
    public function sendReminder(
        Request $request,
        ContratEmbauche $contrat,
        ContractReminderAiService $contractReminderAiService,
        RecrutementNotificationService $notifier
    ): Response
    {
        if (!$this->isCsrfTokenValid('send_reminder_contrat_' . $contrat->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour l envoi du rappel.');

            return $this->redirectToRoute('contrat_embauche_index');
        }

        $dateFin = $contrat->getDateFin();
        if ($dateFin === null) {
            $this->addFlash('error', 'Impossible d envoyer un rappel sans date de fin.');

            return $this->redirectToRoute('contrat_embauche_index');
        }

        $today = new \DateTimeImmutable('today');
        $daysRemaining = (int) $today->diff(\DateTimeImmutable::createFromInterface($dateFin))->format('%r%a');
        if ($daysRemaining < 0) {
            $this->addFlash('error', sprintf('Le contrat #%d est deja expire.', $contrat->getId() ?? 0));

            return $this->redirectToRoute('contrat_embauche_index');
        }

        $message = $contractReminderAiService->generateReminderMessage($contrat, $daysRemaining);

        try {
            $sent = $notifier->notifyContractReminder($contrat, $message, $daysRemaining);
            if (!$sent) {
                $this->addFlash('error', sprintf('Aucun email candidat trouve pour le contrat #%d.', $contrat->getId() ?? 0));
            } else {
                $this->addFlash('success', sprintf('Rappel envoye pour le contrat #%d.', $contrat->getId() ?? 0));
            }
        } catch (\Throwable) {
            $this->addFlash('error', sprintf('Echec de l envoi du rappel pour le contrat #%d.', $contrat->getId() ?? 0));
        }

        return $this->redirectToRoute('contrat_embauche_index');
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

    private function applyContratStatus(ContratEmbauche $contrat): void
    {
        if (!$contrat->getDateDebut() || !$contrat->getDateFin()) {
            return;
        }

        $today = new \DateTime();
        if ($today >= $contrat->getDateDebut() && $today <= $contrat->getDateFin()) {
            $contrat->setStatus('Actif');
        } elseif ($today < $contrat->getDateDebut()) {
            $contrat->setStatus('En attente');
        } else {
            $contrat->setStatus('Termine');
        }
    }
}

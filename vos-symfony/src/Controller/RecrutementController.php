<?php

namespace App\Controller;

use App\Entity\Entretien;
use App\Entity\ContratEmbauche;
use App\Entity\Recrutement;
use App\Entity\User;
use App\Form\RecrutementType;
use App\Service\GoogleCalendarService;
use App\Service\RecrutementNotificationService;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RecrutementController extends AbstractController
{
    #[Route('/admin/recrutements', name: 'recrutement_index')]
    public function index(Request $request, ManagerRegistry $doctrine): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $decision = trim((string) $request->query->get('decision', ''));
        $userIdFilter = (int) $request->query->get('userId', 0);
        $sortBy = (string) $request->query->get('sortBy', 'dateDecision');
        $sortOrder = strtoupper((string) $request->query->get('sortOrder', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $sortMap = [
            'dateDecision' => 'r.dateDecision',
            'decisionFinale' => 'r.decisionFinale',
            'utilisateur' => 'u.nom',
        ];

        /** @var \Doctrine\ORM\EntityRepository $recrutementRepo */
        $recrutementRepo = $doctrine->getRepository(Recrutement::class);
        $qb = $recrutementRepo->createQueryBuilder('r')
            ->leftJoin(User::class, 'u', 'WITH', 'u.id = r.idUtilisateur');

        if ($search !== '') {
            $searchExpr = $qb->expr()->orX(
                'LOWER(r.decisionFinale) LIKE :search',
                'LOWER(u.nom) LIKE :search',
                'LOWER(u.prenom) LIKE :search',
                'LOWER(u.email) LIKE :search'
            );
            $qb->setParameter('search', '%' . strtolower($search) . '%');

            if (ctype_digit($search)) {
                $searchNumber = (int) $search;
                $searchExpr->add('r.idEntretien = :searchNumber');
                $searchExpr->add('r.idUtilisateur = :searchNumber');
                $qb->setParameter('searchNumber', $searchNumber);
            }

            $qb->andWhere($searchExpr);
        }

        if ($decision !== '' && $decision !== 'Tous') {
            $qb->andWhere('r.decisionFinale = :decision')
                ->setParameter('decision', $decision);
        }

        if ($userIdFilter > 0) {
            $qb->andWhere('r.idUtilisateur = :userIdFilter')
                ->setParameter('userIdFilter', $userIdFilter);
        }

        $qb->orderBy($sortMap[$sortBy] ?? $sortMap['dateDecision'], $sortOrder);
        $recrutements = $qb->getQuery()->getResult();

        /** @var \Doctrine\ORM\EntityRepository $recrutementRepo */
        $recrutementRepo = $doctrine->getRepository(Recrutement::class);
        $dynamicUserOptions = $recrutementRepo->createQueryBuilder('r')
            ->select('DISTINCT r.idUtilisateur AS userId, u.nom AS nom, u.prenom AS prenom, u.email AS email')
            ->leftJoin(User::class, 'u', 'WITH', 'u.id = r.idUtilisateur')
            ->andWhere('r.idUtilisateur IS NOT NULL')
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $userOptions = [];
        $userNamesById = [];
        foreach ($dynamicUserOptions as $row) {
            $currentUserId = (int) $row['userId'];
            $fullName = trim(((string) ($row['nom'] ?? '')) . ' ' . ((string) ($row['prenom'] ?? '')));
            $labelName = $fullName !== '' ? $fullName : (string) ($row['email'] ?? 'Utilisateur inconnu');
            $display = $labelName;
            $userOptions[] = [
                'id' => $currentUserId,
                'label' => $display,
            ];
            $userNamesById[$currentUserId] = $display;
        }

        /** @var \Doctrine\ORM\EntityRepository $recrutementRepo */
        $recrutementRepo = $doctrine->getRepository(Recrutement::class);
        $decisionRows = $recrutementRepo->createQueryBuilder('r')
            ->select('DISTINCT r.decisionFinale AS decisionFinale')
            ->andWhere('r.decisionFinale IS NOT NULL')
            ->orderBy('r.decisionFinale', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $decisionOptions = [];
        foreach ($decisionRows as $row) {
            $value = trim((string) ($row['decisionFinale'] ?? ''));
            if ($value !== '') {
                $decisionOptions[] = $value;
            }
        }

        $recrutementStats = [
            'total' => count($recrutements),
            'acceptes' => 0,
            'refuses' => 0,
        ];

        foreach ($recrutements as $recrutement) {
            $decisionNormalized = strtolower(trim((string) ($recrutement->getDecisionFinale() ?? '')));
            if (in_array($decisionNormalized, ['accepté', 'accepte', 'acceptes', 'acceptés'], true)) {
                ++$recrutementStats['acceptes'];
            }
            if (in_array($decisionNormalized, ['refusé', 'refuse', 'refusés', 'refuses'], true)) {
                ++$recrutementStats['refuses'];
            }
        }

        return $this->render('recrutement/index.html.twig', [
            'recrutements' => $recrutements,
            'userNamesById' => $userNamesById,
            'stats' => $recrutementStats,
            'filters' => [
                'search' => $search,
                'decision' => $decision,
                'userId' => $userIdFilter,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
            ],
            'decisionOptions' => $decisionOptions,
            'userOptions' => $userOptions,
        ]);
    }

    #[Route('/admin/recrutements/statistique', name: 'recrutement_statistique')]
    public function statistique(ManagerRegistry $doctrine): Response
    {
        $recrutements = $doctrine->getRepository(Recrutement::class)->findAll();
        $contrats = $doctrine->getRepository(ContratEmbauche::class)->findAll();

        $decisionCounts = [
            'Accepté' => 0,
            'Refusé' => 0,
            'En attente' => 0,
            'Autre' => 0,
        ];

        foreach ($recrutements as $recrutement) {
            $decision = strtolower(trim((string) ($recrutement->getDecisionFinale() ?? '')));
            if (in_array($decision, ['accepté', 'accepte', 'acceptes', 'acceptés'], true)) {
                ++$decisionCounts['Accepté'];
            } elseif (in_array($decision, ['refusé', 'refuse', 'refuses', 'refusés'], true)) {
                ++$decisionCounts['Refusé'];
            } elseif (in_array($decision, ['en attente', 'attente'], true)) {
                ++$decisionCounts['En attente'];
            } else {
                ++$decisionCounts['Autre'];
            }
        }

        $statusCounts = [
            'Actif' => 0,
            'En attente' => 0,
            'Terminé' => 0,
            'Autre' => 0,
        ];

        foreach ($contrats as $contrat) {
            $status = strtolower(trim((string) ($contrat->getStatus() ?? '')));
            if ($status === 'actif') {
                ++$statusCounts['Actif'];
            } elseif (in_array($status, ['en attente', 'attente'], true)) {
                ++$statusCounts['En attente'];
            } elseif (in_array($status, ['termine', 'terminé'], true)) {
                ++$statusCounts['Terminé'];
            } else {
                ++$statusCounts['Autre'];
            }
        }

        $monthLabels = [];
        $monthKeys = [];
        $cursor = new \DateTimeImmutable('first day of -5 month');
        for ($i = 0; $i < 6; ++$i) {
            $monthLabels[] = $cursor->format('M Y');
            $monthKeys[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('+1 month');
        }

        $recrutementsByMonth = array_fill_keys($monthKeys, 0);
        foreach ($recrutements as $recrutement) {
            $date = $recrutement->getDateDecision();
            if ($date !== null) {
                $key = $date->format('Y-m');
                if (array_key_exists($key, $recrutementsByMonth)) {
                    ++$recrutementsByMonth[$key];
                }
            }
        }

        $contratsByMonth = array_fill_keys($monthKeys, 0);
        foreach ($contrats as $contrat) {
            $date = $contrat->getDateDebut();
            if ($date !== null) {
                $key = $date->format('Y-m');
                if (array_key_exists($key, $contratsByMonth)) {
                    ++$contratsByMonth[$key];
                }
            }
        }

        $accepted = $decisionCounts['Accepté'];
        $acceptanceRate = count($recrutements) > 0 ? round(($accepted * 100) / count($recrutements), 1) : 0;

        return $this->render('recrutement/statistique.html.twig', [
            'kpis' => [
                'totalRecrutements' => count($recrutements),
                'totalContrats' => count($contrats),
                'acceptanceRate' => $acceptanceRate,
            ],
            'decisionLabels' => array_keys($decisionCounts),
            'decisionValues' => array_values($decisionCounts),
            'statusLabels' => array_keys($statusCounts),
            'statusValues' => array_values($statusCounts),
            'monthLabels' => $monthLabels,
            'recrutementsSeries' => array_values($recrutementsByMonth),
            'contratsSeries' => array_values($contratsByMonth),
        ]);
    }

    #[Route('/admin/recrutements/new', name: 'recrutement_new')]
    public function new(Request $request, ManagerRegistry $doctrine, RecrutementNotificationService $notifier, GoogleCalendarService $calendar): Response
    {
        $recrutement = new Recrutement();
        $selectedUserId = $this->resolveSelectedUserId($request, $recrutement);

        $form = $this->createForm(RecrutementType::class, $recrutement, [
            'utilisateur_choices' => $this->buildUtilisateurChoices($doctrine),
            'entretien_choices' => $this->buildEntretienChoices($doctrine, $selectedUserId),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();
            $entityManager->persist($recrutement);
            $entityManager->flush();

            if ($calendar->isConfigured()) {
                try {
                    $eventId = $calendar->createRecrutementEvent($recrutement);
                    $recrutement->setCalendarEventId($eventId);
                    $entityManager->flush();
                    $this->addFlash('success', 'Decision ajoutee au Google Calendar.');
                } catch (\Throwable $e) {
                    $this->addFlash('warning', 'Recrutement enregistre, mais echec de synchronisation Google Calendar: ' . $e->getMessage());
                }
            }

            try {
                $notifier->notifyDecision($recrutement);
            } catch (\Throwable) {
                $this->addFlash('warning', 'Recrutement enregistré, mais l\'envoi de la notification a échoué.');
            }

            $this->addFlash('success', 'Recrutement ajoute avec succes.');

            return $this->redirectToRoute('recrutement_index');
        }

        return $this->render('recrutement/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/recrutements/{id}/edit', name: 'recrutement_edit')]
    public function edit(Request $request, ManagerRegistry $doctrine, Recrutement $recrutement, RecrutementNotificationService $notifier, GoogleCalendarService $calendar): Response
    {
        $selectedUserId = $this->resolveSelectedUserId($request, $recrutement);

        $form = $this->createForm(RecrutementType::class, $recrutement, [
            'utilisateur_choices' => $this->buildUtilisateurChoices($doctrine),
            'entretien_choices' => $this->buildEntretienChoices($doctrine, $selectedUserId),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();
            $entityManager->flush();

            $this->syncCalendarOnEdit($calendar, $recrutement, $entityManager);

            try {
                $notifier->notifyDecision($recrutement);
            } catch (\Throwable) {
                $this->addFlash('warning', 'Recrutement mis à jour, mais l\'envoi de la notification a échoué.');
            }

            $this->addFlash('success', 'Recrutement mis a jour avec succes.');

            return $this->redirectToRoute('recrutement_index');
        }

        return $this->render('recrutement/edit.html.twig', [
            'form' => $form->createView(),
            'recrutement' => $recrutement,
        ]);
    }

    #[Route('/admin/recrutements/{id}/delete', name: 'recrutement_delete', methods: ['POST'])]
    public function delete(Request $request, ManagerRegistry $doctrine, Recrutement $recrutement, GoogleCalendarService $calendar): Response
    {
        if ($this->isCsrfTokenValid('delete_recrutement_' . $recrutement->getId(), (string) $request->request->get('_token'))) {
            $entityManager = $doctrine->getManager();
            $calendarEventId = $recrutement->getCalendarEventId();

            try {
                if ($calendarEventId !== null) {
                    try {
                        $calendar->deleteEvent($calendarEventId);
                    } catch (\Throwable) {
                        $this->addFlash('warning', 'Recrutement supprime, mais echec de suppression dans Google Calendar.');
                    }
                }

                $entityManager->remove($recrutement);
                $entityManager->flush();
                $this->addFlash('success', 'Recrutement supprime avec succes.');
            } catch (ForeignKeyConstraintViolationException) {
                $this->addFlash('error', 'Suppression bloquee par contrainte FK. Executez la migration ON DELETE CASCADE.');
            }
        }

        return $this->redirectToRoute('recrutement_index');
    }

    #[Route('/admin/recrutements/entretiens-by-user', name: 'recrutement_entretiens_by_user', methods: ['GET'])]
    public function entretiensByUser(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $userId = (int) $request->query->get('userId', 0);
        if ($userId <= 0) {
            return $this->json([]);
        }

        $choices = $this->buildEntretienChoices($doctrine, $userId);
        $payload = [];
        foreach ($choices as $label => $value) {
            $payload[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $this->json($payload);
    }

    private function buildUtilisateurChoices(ManagerRegistry $doctrine): array
    {
        /** @var \Doctrine\ORM\EntityRepository $entretienRepo */
        /** @var \Doctrine\ORM\EntityRepository $entretienRepo */
        /** @var \Doctrine\ORM\EntityRepository $entretienRepo */
        $entretienRepo = $doctrine->getRepository(Entretien::class);
        /** @var \Doctrine\ORM\EntityRepository $userRepo */
        $userRepo = $doctrine->getRepository(User::class);

        $entretiens = $entretienRepo->createQueryBuilder('e')
            ->select('DISTINCT e.idUtilisateur AS userId')
            ->andWhere('e.idUtilisateur IS NOT NULL')
            ->getQuery()
            ->getArrayResult();

        $userIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['userId'], $entretiens)));
        if ($userIds === []) {
            return [];
        }

        $users = $userRepo->createQueryBuilder('u')
            ->andWhere('u.id IN (:ids)')
            ->setParameter('ids', $userIds)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getResult();

        $choices = [];
        foreach ($users as $user) {
            $fullName = trim((string) ($user->getNom() ?? '') . ' ' . (string) ($user->getPrenom() ?? ''));
            $labelName = $fullName !== '' ? $fullName : (string) ($user->getEmail() ?? 'Utilisateur');
            $choices[sprintf('%s (ID: %d)', $labelName, (int) $user->getId())] = (int) $user->getId();
        }

        return $choices;
    }

    private function buildEntretienChoices(ManagerRegistry $doctrine, ?int $userId = null): array
    {
        $entretienRepo = $doctrine->getRepository(Entretien::class);

        $queryBuilder = $entretienRepo->createQueryBuilder('e')
            ->andWhere('LOWER(e.statutEntretien) IN (:statuts)')
            ->setParameter('statuts', ['termine', 'terminé'])
            ->orderBy('e.dateEntretien', 'DESC')
            ->addOrderBy('e.heureEntretien', 'DESC');

        if ($userId !== null) {
            $queryBuilder
                ->andWhere('e.idUtilisateur = :userId')
                ->setParameter('userId', $userId);
        }

        $entretiens = $queryBuilder->getQuery()->getResult();

        $choices = [];
        foreach ($entretiens as $entretien) {
            $date = $entretien->getDateEntretien()?->format('Y-m-d') ?? 'sans date';
            $type = $entretien->getTypeEntretien() ?: 'type inconnu';
            $choices[sprintf('Entretien #%d - %s - %s', (int) $entretien->getId(), $type, $date)] = (int) $entretien->getId();
        }

        return $choices;
    }

    private function resolveSelectedUserId(Request $request, Recrutement $recrutement): ?int
    {
        $formData = $request->request->all('recrutement');
        $submittedUserId = isset($formData['idUtilisateur']) ? (int) $formData['idUtilisateur'] : 0;
        if ($submittedUserId > 0) {
            return $submittedUserId;
        }

        return $recrutement->getIdUtilisateur();
    }

    private function syncCalendarOnEdit(GoogleCalendarService $calendar, Recrutement $recrutement, \Doctrine\Persistence\ObjectManager $entityManager): void
    {
        if (!$calendar->isConfigured()) {
            return;
        }

        try {
            $existingEventId = $recrutement->getCalendarEventId();
            if ($existingEventId !== null) {
                $calendar->updateRecrutementEvent($existingEventId, $recrutement);
            } else {
                $recrutement->setCalendarEventId($calendar->createRecrutementEvent($recrutement));
                $entityManager->flush();
            }

            $this->addFlash('success', 'Google Calendar mis a jour.');
        } catch (\Throwable) {
            $this->addFlash('warning', 'Recrutement modifie, mais echec de synchronisation Google Calendar.');
        }
    }
}

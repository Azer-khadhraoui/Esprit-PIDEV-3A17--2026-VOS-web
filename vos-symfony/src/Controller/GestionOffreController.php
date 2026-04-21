<?php

namespace App\Controller;

use App\Entity\CritereOffre;
use App\Entity\OffreEmploi;
use App\Service\CriteriaSuggestionAiService;
use App\Service\OffreDescriptionAiService;
use App\Service\OffreNotificationService;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\DompdfBundle\Wrapper\DompdfWrapperInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class GestionOffreController extends AbstractController
{
    public function __construct(
        private readonly CriteriaSuggestionAiService $criteriaSuggestionAiService,
        private readonly OffreDescriptionAiService $offreDescriptionAiService,
        private readonly DompdfWrapperInterface $pdf,
    ) {
    }

    #[Route('/gestion-offre', name: 'gestion_offre_dashboard', methods: ['GET'])]
    public function dashboard(Request $request, EntityManagerInterface $entityManager): Response
    {
        $dashboardData = $this->resolveDashboardOffers($request, $entityManager);
        $offreRepository = $entityManager->getRepository(OffreEmploi::class);
        $offers = $dashboardData['offers'];

        $selectedOfferId = (int) $request->query->get('selectedOfferId', 0);

        if ($selectedOfferId <= 0 && isset($offers[0])) {
            $selectedOfferId = (int) $offers[0]->getIdOffre();
        }

        $totalOffers = (int) $offreRepository->count([]);
        $activeOffers = (int) $offreRepository->count(['statutOffre' => 'OUVERTE']);
        $closedOffers = (int) $offreRepository->count(['statutOffre' => 'FERMEE']);

        return $this->render('gestion_offre/dashboard.html.twig', [
            'offers' => $offers,
            'selectedOfferId' => $selectedOfferId,
            'totalOffers' => $totalOffers,
            'activeOffers' => $activeOffers,
            'closedOffers' => $closedOffers,
            'filters' => [
                'q' => $dashboardData['filters']['q'],
                'statut' => $dashboardData['filters']['statut'],
                'type_contrat' => $dashboardData['filters']['type_contrat'],
                'work_preference' => $dashboardData['filters']['work_preference'],
                'sort_by' => $dashboardData['filters']['sort_by'],
                'sort_order' => $dashboardData['filters']['sort_order'],
            ],
            'filterOptions' => [
                'statuts' => $this->getDistinctOffreValues($entityManager, 'statut_offre'),
                'typesContrat' => $this->getDistinctOffreValues($entityManager, 'type_contrat'),
                'workPreferences' => $this->getDistinctOffreValues($entityManager, 'work_preference'),
            ],
            'currentUserName' => 'khadhraoui azer',
        ]);
    }

    #[Route('/gestion-offre/export/pdf', name: 'gestion_offre_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, EntityManagerInterface $entityManager): Response
    {
        $dashboardData = $this->resolveDashboardOffers($request, $entityManager);

        $html = $this->renderView('gestion_offre/dashboard_export_pdf.html.twig', [
            'offers' => $dashboardData['offers'],
            'filters' => $dashboardData['filters'],
            'generatedAt' => new \DateTimeImmutable(),
            'currentUserName' => 'khadhraoui azer',
        ]);

        return new Response(
            $this->pdf->getPdf($html),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="offres-export.pdf"',
            ]
        );
    }

    #[Route('/gestion-offre/statistique', name: 'gestion_offre_statistique', methods: ['GET'])]
    public function statistique(EntityManagerInterface $entityManager): Response
    {
        $offreRepository = $entityManager->getRepository(OffreEmploi::class);

        $totalOffers = (int) $offreRepository->count([]);
        $activeOffers = (int) $offreRepository->count(['statutOffre' => 'OUVERTE']);
        $closedOffers = (int) $offreRepository->count(['statutOffre' => 'FERMEE']);

        $statutStats = $entityManager->getConnection()->executeQuery(
            "SELECT COALESCE(statut_offre, 'N/A') AS label, COUNT(*) AS total FROM offre_emploi GROUP BY statut_offre ORDER BY total DESC"
        )->fetchAllAssociative();

        $typeStats = $entityManager->getConnection()->executeQuery(
            "SELECT COALESCE(type_contrat, 'N/A') AS label, COUNT(*) AS total FROM offre_emploi GROUP BY type_contrat ORDER BY total DESC"
        )->fetchAllAssociative();

        $workPreferenceStats = $entityManager->getConnection()->executeQuery(
            "SELECT COALESCE(work_preference, 'N/A') AS label, COUNT(*) AS total FROM offre_emploi GROUP BY work_preference ORDER BY total DESC"
        )->fetchAllAssociative();

        $months = [];
        $monthKeys = [];
        $cursor = new \DateTimeImmutable('first day of this month');
        for ($i = 5; $i >= 0; --$i) {
            $monthDate = $cursor->modify(sprintf('-%d months', $i));
            $monthKeys[] = $monthDate->format('Y-m');
            $months[] = $monthDate->format('M Y');
        }

        $evolutionRows = $entityManager->getConnection()->executeQuery(
            "
                SELECT
                    DATE_FORMAT(date_publication, '%Y-%m') AS month_key,
                    COUNT(*) AS total_offres,
                                        SUM(CASE WHEN UPPER(COALESCE(statut_offre, '')) = 'OUVERTE' THEN 1 ELSE 0 END) AS active_offres
                FROM offre_emploi
                WHERE date_publication IS NOT NULL
                  AND date_publication >= :startDate
                GROUP BY month_key
                ORDER BY month_key ASC
            ",
            ['startDate' => $cursor->modify('-5 months')->format('Y-m-01')]
        )->fetchAllAssociative();

        $evolutionMap = [];
        foreach ($evolutionRows as $row) {
            $monthKey = (string) ($row['month_key'] ?? '');
            if ($monthKey === '') {
                continue;
            }

            $evolutionMap[$monthKey] = [
                'total' => (int) ($row['total_offres'] ?? 0),
                'active' => (int) ($row['active_offres'] ?? 0),
            ];
        }

        $offersSeries = [];
        $activeSeries = [];
        foreach ($monthKeys as $monthKey) {
            $monthStats = $evolutionMap[$monthKey] ?? ['total' => 0, 'active' => 0];
            $offersSeries[] = (int) $monthStats['total'];
            $activeSeries[] = (int) $monthStats['active'];
        }

        return $this->render('gestion_offre/statistique.html.twig', [
            'totalOffers' => $totalOffers,
            'activeOffers' => $activeOffers,
            'closedOffers' => $closedOffers,
            'statutStats' => $statutStats,
            'typeStats' => $typeStats,
            'workPreferenceStats' => $workPreferenceStats,
            'monthLabels' => $months,
            'offersSeries' => $offersSeries,
            'activeSeries' => $activeSeries,
            'currentUserName' => 'khadhraoui azer',
        ]);
    }

    #[Route('/gestion-offre/new', name: 'gestion_offre_new', methods: ['GET', 'POST'])]
    public function newOffre(
        Request $request,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        OffreNotificationService $offreNotificationService,
    ): Response
    {
        $offre = new OffreEmploi();
        $adminUserId = $this->requireAdminUserId($session);
        if ($adminUserId === null) {
            return $this->redirectToRoute('app_signin');
        }

        $userOptions = $this->getCurrentAdminUserOption($entityManager, $adminUserId);
        if ($userOptions === []) {
            $this->addFlash('error', 'Session administrateur invalide. Veuillez vous reconnecter.');

            return $this->redirectToRoute('app_signin');
        }

        $csrfTokenId = 'offre_form_new';
        $sendNotification = true;

        if ($request->isMethod('POST')) {
            $sendNotification = (string) $request->request->get('send_notification', '0') === '1';
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid($csrfTokenId, $token)) {
                $this->addFlash('error', 'Token invalide. Veuillez reessayer.');

                return $this->render('gestion_offre/offre_form.html.twig', [
                    'offre' => $offre,
                    'pageTitle' => 'Ajouter Offre',
                    'submitLabel' => 'Ajouter',
                    'userOptions' => $userOptions,
                    'fieldErrors' => ['_form' => 'Token invalide. Veuillez reessayer.'],
                    'sendNotification' => $sendNotification,
                    'csrfTokenId' => $csrfTokenId,
                    'currentUserName' => 'khadhraoui azer',
                ]);
            }

            $this->hydrateOffreFromRequest($offre, $request, $adminUserId);
            $errors = $this->validateOffreInput($offre, $entityManager, $adminUserId);

            if ($errors !== []) {
                return $this->render('gestion_offre/offre_form.html.twig', [
                    'offre' => $offre,
                    'pageTitle' => 'Ajouter Offre',
                    'submitLabel' => 'Ajouter',
                    'userOptions' => $userOptions,
                    'fieldErrors' => $errors,
                    'sendNotification' => $sendNotification,
                    'csrfTokenId' => $csrfTokenId,
                    'currentUserName' => 'khadhraoui azer',
                ]);
            }

            $entityManager->persist($offre);
            $entityManager->flush();

            if ($sendNotification) {
                $mailingStats = $offreNotificationService->notifyClientsForNewOffer($offre);

                if ($mailingStats['total'] === 0) {
                    $this->addFlash('warning', 'Offre ajoutee avec succes. Aucun compte client a notifier.');
                } elseif ($mailingStats['failed'] > 0) {
                    $this->addFlash(
                        'warning',
                        sprintf(
                            'Offre ajoutee avec succes. Notifications envoyees: %d/%d clients.',
                            $mailingStats['sent'],
                            $mailingStats['total'],
                        ),
                    );
                } else {
                    $this->addFlash(
                        'success',
                        sprintf('Offre ajoutee avec succes. Notification envoyee a %d client(s).', $mailingStats['sent']),
                    );
                }
            } else {
                $this->addFlash('success', 'Offre ajoutee avec succes. Notification email desactivee.');
            }

            return $this->redirectToRoute('gestion_offre_dashboard');
        }

        return $this->render('gestion_offre/offre_form.html.twig', [
            'offre' => $offre,
            'pageTitle' => 'Ajouter Offre',
            'submitLabel' => 'Ajouter',
            'userOptions' => $userOptions,
            'fieldErrors' => [],
            'sendNotification' => $sendNotification,
            'csrfTokenId' => $csrfTokenId,
            'currentUserName' => 'khadhraoui azer',
        ]);
    }

    #[Route('/gestion-offre/{idOffre}/edit', name: 'gestion_offre_edit', methods: ['GET', 'POST'])]
    public function editOffre(
        #[MapEntity(id: 'idOffre')] OffreEmploi $offre,
        Request $request,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
    ): Response {
        $adminUserId = $this->requireAdminUserId($session);
        if ($adminUserId === null) {
            return $this->redirectToRoute('app_signin');
        }

        $userOptions = $this->getCurrentAdminUserOption($entityManager, $adminUserId);
        if ($userOptions === []) {
            $this->addFlash('error', 'Session administrateur invalide. Veuillez vous reconnecter.');

            return $this->redirectToRoute('app_signin');
        }

        $csrfTokenId = 'offre_form_edit_'.$offre->getIdOffre();

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid($csrfTokenId, $token)) {
                $this->addFlash('error', 'Token invalide. Veuillez reessayer.');

                return $this->render('gestion_offre/offre_form.html.twig', [
                    'offre' => $offre,
                    'pageTitle' => 'Modifier Offre',
                    'submitLabel' => 'Mettre a jour',
                    'userOptions' => $userOptions,
                    'fieldErrors' => ['_form' => 'Token invalide. Veuillez reessayer.'],
                    'csrfTokenId' => $csrfTokenId,
                    'currentUserName' => 'khadhraoui azer',
                ]);
            }

            $this->hydrateOffreFromRequest($offre, $request, $adminUserId);
            $errors = $this->validateOffreInput($offre, $entityManager, $adminUserId);

            if ($errors !== []) {
                return $this->render('gestion_offre/offre_form.html.twig', [
                    'offre' => $offre,
                    'pageTitle' => 'Modifier Offre',
                    'submitLabel' => 'Mettre a jour',
                    'userOptions' => $userOptions,
                    'fieldErrors' => $errors,
                    'csrfTokenId' => $csrfTokenId,
                    'currentUserName' => 'khadhraoui azer',
                ]);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Offre modifiee avec succes.');

            return $this->redirectToRoute('gestion_offre_dashboard');
        }

        return $this->render('gestion_offre/offre_form.html.twig', [
            'offre' => $offre,
            'pageTitle' => 'Modifier Offre',
            'submitLabel' => 'Mettre a jour',
            'userOptions' => $userOptions,
            'fieldErrors' => [],
            'csrfTokenId' => $csrfTokenId,
            'currentUserName' => 'khadhraoui azer',
        ]);
    }

    #[Route('/gestion-offre/{idOffre}/delete', name: 'gestion_offre_delete', methods: ['POST'])]
    public function deleteOffre(
        #[MapEntity(id: 'idOffre')] OffreEmploi $offre,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $token = (string) $request->request->get('_token');

        if (!$this->isCsrfTokenValid('delete_offer_'.$offre->getIdOffre(), $token)) {
            $this->addFlash('error', 'Token invalide. Suppression refusee.');

            return $this->redirectToRoute('gestion_offre_dashboard');
        }

        try {
            $entityManager->remove($offre);
            $entityManager->flush();
            $this->addFlash('success', 'Offre supprimee avec succes.');
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('error', 'Suppression bloquee par contrainte FK. Activez ON DELETE CASCADE pour candidature.');
        }

        return $this->redirectToRoute('gestion_offre_dashboard');
    }

    #[Route('/gestion-offre/critere/create', name: 'gestion_offre_critere_create', methods: ['POST'])]
    public function createCritere(Request $request, EntityManagerInterface $entityManager): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('create_critere_offer', $token)) {
            $this->addFlash('error', 'Token invalide. Veuillez reessayer.');

            return $this->redirectToRoute('gestion_offre_dashboard');
        }

        $offerId = (int) $request->request->get('id_offre');
        $offre = $entityManager->getRepository(OffreEmploi::class)->find($offerId);

        if (!$offre) {
            $this->addFlash('error', 'Offre introuvable pour ajouter le critere.');

            return $this->redirectToRoute('gestion_offre_dashboard');
        }

        $niveauExperience = $this->normalizeText($request->request->get('niveau_experience'));
        $niveauEtude = $this->normalizeText($request->request->get('niveau_etude'));
        $competencesRequises = $this->normalizeText($request->request->get('competences_requises'));
        $responsibilities = $this->normalizeText($request->request->get('responsibilities'));

        $errors = $this->validateCritereInput(
            $niveauExperience,
            $niveauEtude,
            $competencesRequises,
            $responsibilities,
        );

        if ($errors !== []) {
            if ($request->request->get('context') === 'offer') {
                $criteria = $entityManager->getRepository(CritereOffre::class)->findBy(
                    ['offreEmploi' => $offre],
                    ['idCritere' => 'DESC'],
                );

                return $this->render('gestion_offre/criteres.html.twig', [
                    'offre' => $offre,
                    'criteria' => $criteria,
                    'fieldErrors' => $errors,
                    'createFormData' => [
                        'niveau_experience' => $niveauExperience,
                        'niveau_etude' => $niveauEtude,
                        'competences_requises' => $competencesRequises,
                        'responsibilities' => $responsibilities,
                    ],
                    'currentUserName' => 'khadhraoui azer',
                ]);
            }

            return $this->redirectToRoute('gestion_offre_dashboard');
        }

        $critere = new CritereOffre();
        $critere->setOffreEmploi($offre);
        $this->hydrateCritereFromValues(
            $critere,
            $niveauExperience,
            $niveauEtude,
            $competencesRequises,
            $responsibilities,
        );

        $entityManager->persist($critere);
        $entityManager->flush();

        $this->addFlash('success', 'Critere offre ajoute avec succes.');

        if ($request->request->get('context') === 'offer') {
            return $this->redirectToRoute('gestion_offre_criteres', ['idOffre' => $offre->getIdOffre()]);
        }

        return $this->redirectToRoute('gestion_offre_dashboard');
    }

    #[Route('/gestion-offre/critere/{idCritere}/edit', name: 'gestion_offre_critere_edit', methods: ['GET', 'POST'])]
    public function editCritere(
        #[MapEntity(id: 'idCritere')] CritereOffre $critere,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $offre = $critere->getOffreEmploi();
        if ($offre === null) {
            $this->addFlash('error', 'Critere sans offre associee.');

            return $this->redirectToRoute('gestion_offre_dashboard');
        }

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('edit_critere_'.$critere->getIdCritere(), $token)) {
                $this->addFlash('error', 'Token invalide. Modification du critere refusee.');

                return $this->redirectToRoute('gestion_offre_criteres', ['idOffre' => $offre->getIdOffre()]);
            }

            $niveauExperience = $this->normalizeText($request->request->get('niveau_experience'));
            $niveauEtude = $this->normalizeText($request->request->get('niveau_etude'));
            $competencesRequises = $this->normalizeText($request->request->get('competences_requises'));
            $responsibilities = $this->normalizeText($request->request->get('responsibilities'));

            $errors = $this->validateCritereInput(
                $niveauExperience,
                $niveauEtude,
                $competencesRequises,
                $responsibilities,
            );

            if ($errors !== []) {
                $this->hydrateCritereFromValues(
                    $critere,
                    $niveauExperience,
                    $niveauEtude,
                    $competencesRequises,
                    $responsibilities,
                );

                return $this->render('gestion_offre/critere_form.html.twig', [
                    'offre' => $offre,
                    'critere' => $critere,
                    'fieldErrors' => $errors,
                    'pageTitle' => 'Modifier critere',
                    'submitLabel' => 'Mettre a jour',
                    'currentUserName' => 'khadhraoui azer',
                ]);
            }

            $this->hydrateCritereFromValues(
                $critere,
                $niveauExperience,
                $niveauEtude,
                $competencesRequises,
                $responsibilities,
            );

            $entityManager->flush();

            $this->addFlash('success', 'Critere modifie avec succes.');

            return $this->redirectToRoute('gestion_offre_criteres', ['idOffre' => $offre->getIdOffre()]);
        }

        return $this->render('gestion_offre/critere_form.html.twig', [
            'offre' => $offre,
            'critere' => $critere,
            'fieldErrors' => [],
            'pageTitle' => 'Modifier critere',
            'submitLabel' => 'Mettre a jour',
            'currentUserName' => 'khadhraoui azer',
        ]);
    }

    #[Route('/gestion-offre/critere/{idCritere}/delete', name: 'gestion_offre_critere_delete', methods: ['POST'])]
    public function deleteCritere(
        #[MapEntity(id: 'idCritere')] CritereOffre $critere,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $offre = $critere->getOffreEmploi();
        $offreId = $offre?->getIdOffre();

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_critere_'.$critere->getIdCritere(), $token)) {
            $this->addFlash('error', 'Token invalide. Suppression du critere refusee.');

            if ($offreId !== null) {
                return $this->redirectToRoute('gestion_offre_criteres', ['idOffre' => $offreId]);
            }

            return $this->redirectToRoute('gestion_offre_dashboard');
        }

        $entityManager->remove($critere);
        $entityManager->flush();

        $this->addFlash('success', 'Critere supprime avec succes.');

        if ($offreId !== null) {
            return $this->redirectToRoute('gestion_offre_criteres', ['idOffre' => $offreId]);
        }

        return $this->redirectToRoute('gestion_offre_dashboard');
    }

    #[Route('/gestion-offre/{idOffre}/criteres', name: 'gestion_offre_criteres', methods: ['GET'])]
    public function criteresByOffre(
        #[MapEntity(id: 'idOffre')] OffreEmploi $offre,
        EntityManagerInterface $entityManager,
    ): Response {
        $criteria = $entityManager->getRepository(CritereOffre::class)->findBy(
            ['offreEmploi' => $offre],
            ['idCritere' => 'DESC'],
        );

        return $this->render('gestion_offre/criteres.html.twig', [
            'offre' => $offre,
            'criteria' => $criteria,
            'fieldErrors' => [],
            'createFormData' => [],
            'currentUserName' => 'khadhraoui azer',
        ]);
    }

    #[Route('/gestion-offre/criteres/suggestions', name: 'gestion_offre_criteres_suggestions', methods: ['GET'])]
    public function suggestCritere(Request $request, SessionInterface $session): JsonResponse
    {
        $adminUserId = $this->requireAdminUserId($session);
        if ($adminUserId === null) {
            return $this->json([
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $title = $this->normalizeText($request->query->get('titre'));
        $niveauExperience = $this->normalizeText($request->query->get('niveau_experience'));
        $niveauEtude = $this->normalizeText($request->query->get('niveau_etude'));

        if ($title === null || $niveauExperience === null || $niveauEtude === null) {
            return $this->json([
                'message' => 'titre, niveau_experience et niveau_etude sont obligatoires.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $aiSuggestion = $this->criteriaSuggestionAiService->suggest($title, $niveauExperience, $niveauEtude);
        if ($aiSuggestion !== null) {
            return $this->json($aiSuggestion);
        }

        return $this->json([
            'competences_requises' => $this->buildCompetencesSuggestion($title, $niveauExperience, $niveauEtude),
            'responsibilities' => $this->buildResponsibilitiesSuggestion($title, $niveauExperience, $niveauEtude),
        ]);
    }

    #[Route('/gestion-offre/description/suggestion', name: 'gestion_offre_description_suggestion', methods: ['GET'])]
    public function suggestOffreDescription(Request $request, SessionInterface $session): JsonResponse
    {
        $adminUserId = $this->requireAdminUserId($session);
        if ($adminUserId === null) {
            return $this->json([
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $title = $this->normalizeText($request->query->get('titre'));
        if ($title === null || mb_strlen($title) < 3) {
            return $this->json([
                'message' => 'Le titre est obligatoire (min 3 caracteres).',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $description = $this->offreDescriptionAiService->suggest($title);
        if ($description === null) {
            $description = $this->buildOffreDescriptionFallback($title);
        }

        return $this->json([
            'description' => $description,
        ]);
    }

    private function normalizeText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{
     *   offers:list<OffreEmploi>,
     *   filters:array{q:?string, statut:?string, type_contrat:?string, work_preference:?string, sort_by:string, sort_order:string}
     * }
     */
    private function resolveDashboardOffers(Request $request, EntityManagerInterface $entityManager): array
    {
        $offreRepository = $entityManager->getRepository(OffreEmploi::class);
        $search = $this->normalizeText($request->query->get('q'));
        $statutFilter = $this->normalizeText($request->query->get('statut'));
        if ($statutFilter === 'ACTIVE') {
            $statutFilter = 'OUVERTE';
        }
        $typeContratFilter = $this->normalizeText($request->query->get('type_contrat'));
        $workPreferenceFilter = $this->normalizeText($request->query->get('work_preference'));
        $sortBy = $this->normalizeText($request->query->get('sortBy')) ?? 'date_publication';
        $sortOrder = strtoupper($this->normalizeText($request->query->get('sortOrder')) ?? 'DESC');

        $sortMap = [
            'id' => 'o.idOffre',
            'titre' => 'o.titre',
            'type_contrat' => 'o.typeContrat',
            'work_preference' => 'o.workPreference',
            'lieu' => 'o.lieu',
            'statut' => 'o.statutOffre',
            'date_publication' => 'o.datePublication',
        ];

        if (!isset($sortMap[$sortBy])) {
            $sortBy = 'date_publication';
        }

        if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
            $sortOrder = 'DESC';
        }

        $qb = $offreRepository->createQueryBuilder('o');

        if ($search !== null) {
            $qb
                ->andWhere('o.titre LIKE :q OR o.description LIKE :q OR o.lieu LIKE :q')
                ->setParameter('q', '%'.$search.'%');
        }

        if ($statutFilter !== null) {
            $qb
                ->andWhere('o.statutOffre = :statut')
                ->setParameter('statut', $statutFilter);
        }

        if ($typeContratFilter !== null) {
            $qb
                ->andWhere('o.typeContrat = :typeContrat')
                ->setParameter('typeContrat', $typeContratFilter);
        }

        if ($workPreferenceFilter !== null) {
            $qb
                ->andWhere('o.workPreference = :workPreference')
                ->setParameter('workPreference', $workPreferenceFilter);
        }

        $offers = $qb
            ->orderBy($sortMap[$sortBy], $sortOrder)
            ->getQuery()
            ->getResult();

        return [
            'offers' => $offers,
            'filters' => [
                'q' => $search,
                'statut' => $statutFilter,
                'type_contrat' => $typeContratFilter,
                'work_preference' => $workPreferenceFilter,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ];
    }

    private function buildCompetencesSuggestion(string $title, string $niveauExperience, string $niveauEtude): string
    {
        $roleFamily = $this->detectRoleFamily($title);

        $baseSkillsByFamily = [
            'backend' => ['API REST', 'Conception orientee objet', 'SQL', 'Git', 'Tests unitaires', 'Clean code'],
            'frontend' => ['HTML5', 'CSS3', 'JavaScript', 'TypeScript', 'Responsive Design', 'Git'],
            'fullstack' => ['Architecture web', 'API REST', 'JavaScript', 'SQL', 'Git', 'CI/CD'],
            'data' => ['Python', 'SQL', 'Data analysis', 'Data visualization', 'Statistiques', 'Reporting'],
            'devops' => ['Docker', 'CI/CD', 'Linux', 'Monitoring', 'Cloud', 'Infrastructure as Code'],
            'default' => ['Communication', 'Resolution de problemes', 'Travail en equipe', 'Organisation', 'Autonomie'],
        ];

        $skills = $baseSkillsByFamily[$roleFamily] ?? $baseSkillsByFamily['default'];
        $skills = array_merge($skills, $this->extractSkillsFromTitle($title));

        if ($this->isSeniorExperience($niveauExperience)) {
            $skills[] = 'Architecture logicielle';
            $skills[] = 'Revue de code';
            $skills[] = 'Mentorat technique';
        } elseif ($this->isJuniorExperience($niveauExperience)) {
            $skills[] = 'Bonnes pratiques de developpement';
            $skills[] = 'Capacite d apprentissage rapide';
        }

        if (str_contains(mb_strtolower($niveauEtude), 'master') || str_contains(mb_strtolower($niveauEtude), 'ingenieur')) {
            $skills[] = 'Conception de solutions complexes';
            $skills[] = 'Modelisation et documentation technique';
        }

        $skills = array_values(array_unique($skills));

        return implode("\n", array_map(static fn (string $skill): string => '- '.$skill, $skills));
    }

    private function buildResponsibilitiesSuggestion(string $title, string $niveauExperience, string $niveauEtude): string
    {
        $roleFamily = $this->detectRoleFamily($title);
        $titleSkills = array_slice($this->extractSkillsFromTitle($title), 0, 3);

        $baseResponsibilitiesByFamily = [
            'backend' => [
                'Concevoir et developper des fonctionnalites backend robustes.',
                'Concevoir, documenter et maintenir des API performantes.',
                'Collaborer avec les equipes frontend et produit.',
            ],
            'frontend' => [
                'Developper des interfaces utilisateur responsives et accessibles.',
                'Transformer les maquettes en composants reutilisables.',
                'Collaborer avec UX/UI pour ameliorer l experience client.',
            ],
            'fullstack' => [
                'Prendre en charge des fonctionnalites de bout en bout.',
                'Contribuer au backend, frontend et a l integration.',
                'Participer aux revues de code et aux tests applicatifs.',
            ],
            'data' => [
                'Collecter, nettoyer et analyser les donnees metier.',
                'Produire des tableaux de bord et recommandations actionnables.',
                'Collaborer avec les equipes pour definir les KPIs.',
            ],
            'devops' => [
                'Automatiser les pipelines de build, test et deploiement.',
                'Surveiller la disponibilite et les performances des environnements.',
                'Ameliorer la securite et la fiabilite de l infrastructure.',
            ],
            'default' => [
                'Contribuer a la realisation des objectifs de l equipe.',
                'Assurer la qualite des livrables et le respect des delais.',
                'Communiquer regulierement sur l avancement des travaux.',
            ],
        ];

        $responsibilities = $baseResponsibilitiesByFamily[$roleFamily] ?? $baseResponsibilitiesByFamily['default'];
        $responsibilities = array_merge($responsibilities, $this->buildTitleBasedResponsibilities($title));

        if ($titleSkills !== []) {
            $responsibilities[] = 'Utiliser au quotidien '.implode(', ', $titleSkills).' dans un contexte de production.';
        }

        if ($this->isSeniorExperience($niveauExperience)) {
            $responsibilities[] = 'Encadrer les profils juniors et contribuer aux choix techniques.';
        } elseif ($this->isJuniorExperience($niveauExperience)) {
            $responsibilities[] = 'Participer activement a la montee en competence sous supervision.';
        }

        if (str_contains(mb_strtolower($niveauEtude), 'licence')) {
            $responsibilities[] = 'Appliquer les fondamentaux techniques avec rigueur et progression continue.';
        }

        return implode(' ', array_values(array_unique($responsibilities)));
    }

    /**
     * @return list<string>
     */
    private function extractSkillsFromTitle(string $title): array
    {
        $normalizedTitle = mb_strtolower($title);
        $skillRules = [
            'python' => ['Python', 'Django', 'FastAPI', 'Pandas'],
            'django' => ['Python', 'Django', 'Django REST Framework'],
            'fastapi' => ['Python', 'FastAPI', 'OpenAPI'],
            'php' => ['PHP', 'Composer', 'Unit testing'],
            'symfony' => ['Symfony', 'Doctrine ORM', 'Twig'],
            'laravel' => ['Laravel', 'Eloquent ORM', 'PHP'],
            'java' => ['Java', 'Spring Boot', 'JPA'],
            'spring' => ['Spring Boot', 'Microservices', 'JUnit'],
            'c#' => ['C#', '.NET', 'Entity Framework'],
            '.net' => ['C#', '.NET', 'ASP.NET Core'],
            'node' => ['Node.js', 'Express.js', 'REST API'],
            'react' => ['React', 'TypeScript', 'State management'],
            'angular' => ['Angular', 'TypeScript', 'RxJS'],
            'vue' => ['Vue.js', 'JavaScript', 'Component architecture'],
            'flutter' => ['Flutter', 'Dart', 'Mobile architecture'],
            'android' => ['Kotlin', 'Android SDK', 'Mobile testing'],
            'ios' => ['Swift', 'iOS SDK', 'Mobile architecture'],
            'devops' => ['Docker', 'Kubernetes', 'CI/CD'],
            'aws' => ['AWS', 'Cloud security', 'Monitoring'],
            'azure' => ['Azure', 'Cloud services', 'Monitoring'],
            'gcp' => ['GCP', 'Cloud services', 'Monitoring'],
            'data' => ['Python', 'SQL', 'Data visualization'],
            'analyst' => ['SQL', 'Power BI', 'Reporting'],
            'ai' => ['Machine Learning', 'Python', 'Model evaluation'],
            'ml' => ['Machine Learning', 'Feature engineering', 'Model deployment'],
        ];

        $skills = [];
        foreach ($skillRules as $keyword => $mappedSkills) {
            if (str_contains($normalizedTitle, $keyword)) {
                $skills = array_merge($skills, $mappedSkills);
            }
        }

        if ($skills === []) {
            if ($this->containsAny($normalizedTitle, ['developpeur', 'developer', 'engineer'])) {
                $skills = ['Git', 'Algorithmique', 'Tests unitaires'];
            }
        }

        return array_values(array_unique($skills));
    }

    /**
     * @return list<string>
     */
    private function buildTitleBasedResponsibilities(string $title): array
    {
        $normalizedTitle = mb_strtolower($title);
        $items = [];

        if ($this->containsAny($normalizedTitle, ['python', 'django', 'fastapi'])) {
            $items[] = 'Developper des services backend Python maintenables et tests.';
        }

        if ($this->containsAny($normalizedTitle, ['react', 'angular', 'vue', 'frontend', 'front'])) {
            $items[] = 'Construire des composants UI performants et reutilisables.';
        }

        if ($this->containsAny($normalizedTitle, ['data', 'analyst', 'bi'])) {
            $items[] = 'Fournir des analyses metier et tableaux de bord exploitables.';
        }

        if ($this->containsAny($normalizedTitle, ['devops', 'cloud', 'sre', 'aws', 'azure', 'gcp'])) {
            $items[] = 'Ameliorer les pipelines CI/CD et la fiabilite de la plateforme.';
        }

        if ($this->containsAny($normalizedTitle, ['mobile', 'android', 'ios', 'flutter'])) {
            $items[] = 'Garantir une experience mobile fluide et stable en production.';
        }

        return array_values(array_unique($items));
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function detectRoleFamily(string $title): string
    {
        $normalizedTitle = mb_strtolower($title);

        if (str_contains($normalizedTitle, 'fullstack') || str_contains($normalizedTitle, 'full stack')) {
            return 'fullstack';
        }

        if (str_contains($normalizedTitle, 'backend') || str_contains($normalizedTitle, 'api')) {
            return 'backend';
        }

        if (str_contains($normalizedTitle, 'front') || str_contains($normalizedTitle, 'ui') || str_contains($normalizedTitle, 'ux')) {
            return 'frontend';
        }

        if (str_contains($normalizedTitle, 'data') || str_contains($normalizedTitle, 'bi') || str_contains($normalizedTitle, 'analyst')) {
            return 'data';
        }

        if (str_contains($normalizedTitle, 'devops') || str_contains($normalizedTitle, 'sre') || str_contains($normalizedTitle, 'cloud')) {
            return 'devops';
        }

        return 'default';
    }

    private function isSeniorExperience(string $niveauExperience): bool
    {
        $normalized = mb_strtolower($niveauExperience);

        return str_contains($normalized, 'senior')
            || str_contains($normalized, '5')
            || str_contains($normalized, '6')
            || str_contains($normalized, '7')
            || str_contains($normalized, '8')
            || str_contains($normalized, '9');
    }

    private function isJuniorExperience(string $niveauExperience): bool
    {
        $normalized = mb_strtolower($niveauExperience);

        return str_contains($normalized, 'junior')
            || str_contains($normalized, 'debutant')
            || str_contains($normalized, '0')
            || str_contains($normalized, '1')
            || str_contains($normalized, '2');
    }

    private function buildOffreDescriptionFallback(string $title): string
    {
        $normalized = mb_strtolower($title);
        $family = $this->detectRoleFamily($title);

        $context = match ($family) {
            'backend' => 'Vous contribuerez a la conception de services backend evolutifs et a l optimisation des APIs metier.',
            'frontend' => 'Vous developperez des interfaces modernes, performantes et orientees experience utilisateur.',
            'fullstack' => 'Vous interviendrez sur l ensemble de la chaine applicative, du backend au frontend.',
            'data' => 'Vous transformerez les donnees en insights actionnables pour orienter les decisions metier.',
            'devops' => 'Vous renforcerez la fiabilite des environnements, l automatisation et la qualite des deploiements.',
            default => 'Vous participerez a la realisation de projets a fort impact en collaboration avec les equipes metier et techniques.',
        };

        $impact = 'Le poste de '.$title.' vise a accelerer la livraison de valeur, garantir la qualite des solutions et soutenir les objectifs de croissance.';

        $collaboration = 'Vous travaillerez en coordination avec les equipes produit, QA et engineering dans un cadre agile.';

        if (str_contains($normalized, 'stage') || str_contains($normalized, 'intern')) {
            $collaboration = 'Vous serez accompagne pour monter en competence tout en contribuant concretement aux livrables de l equipe.';
        }

        return trim($impact.' '.$context.' '.$collaboration);
    }

    private function hydrateOffreFromRequest(OffreEmploi $offre, Request $request, int $adminUserId): void
    {
        $datePublicationInput = $this->normalizeText($request->request->get('date_publication'));
        $datePublication = null;
        if ($datePublicationInput !== null) {
            try {
                $datePublication = new \DateTime($datePublicationInput);
            } catch (\Exception) {
                $datePublication = null;
            }
        }

        $offre
            ->setTitre($this->normalizeText($request->request->get('titre')))
            ->setDescription($this->normalizeText($request->request->get('description')))
            ->setTypeContrat($this->normalizeText($request->request->get('type_contrat')))
            ->setStatutOffre($this->normalizeText($request->request->get('statut_offre')))
            ->setDatePublication($datePublication)
            ->setIdUtilisateur($adminUserId)
            ->setWorkPreference($this->normalizeText($request->request->get('work_preference')))
            ->setLieu($this->normalizeText($request->request->get('lieu')));
    }

    /**
     * @return list<string>
     */
    private function validateOffreInput(OffreEmploi $offre, EntityManagerInterface $entityManager, int $adminUserId): array
    {
        $errors = [];

        $titre = $offre->getTitre();
        if ($titre === null || mb_strlen($titre) < 3) {
            $errors['titre'] = 'Le titre est obligatoire et doit contenir au moins 3 caracteres.';
        }
        if ($titre !== null && mb_strlen($titre) > 100) {
            $errors['titre'] = 'Le titre ne doit pas depasser 100 caracteres.';
        }

        $allowedTypeContrat = ['CDI', 'CDD', 'Stage', 'Alternance', 'Freelance'];
        $typeContrat = $offre->getTypeContrat();
        if ($typeContrat === null || !in_array($typeContrat, $allowedTypeContrat, true)) {
            $errors['type_contrat'] = 'Type contrat invalide. Choisissez une valeur de la liste.';
        }

        $allowedStatut = ['OUVERTE', 'FERMEE', 'INACTIVE'];
        $statut = $offre->getStatutOffre();
        if ($statut === null || !in_array($statut, $allowedStatut, true)) {
            $errors['statut_offre'] = 'Statut invalide. Choisissez une valeur de la liste.';
        }

        $allowedWorkPreference = ['On-site', 'Remote', 'Hybrid'];
        $workPreference = $offre->getWorkPreference();
        if ($workPreference === null || !in_array($workPreference, $allowedWorkPreference, true)) {
            $errors['work_preference'] = 'Work preference invalide. Choisissez une valeur de la liste.';
        }

        if ($offre->getDatePublication() === null) {
            $errors['date_publication'] = 'La date de publication est obligatoire.';
        } elseif ($offre->getDatePublication() > new \DateTime('today +1 day')) {
            $errors['date_publication'] = 'La date de publication ne peut pas etre dans le futur.';
        }

        $idUtilisateur = $offre->getIdUtilisateur();
        if ($idUtilisateur === null || $idUtilisateur !== $adminUserId || !$this->userExists($entityManager, $idUtilisateur)) {
            $errors['id_utilisateur'] = 'Veuillez choisir un utilisateur existant.';
        }

        $lieu = $offre->getLieu();
        if ($lieu === null || $lieu === '') {
            $errors['lieu'] = 'Le lieu est obligatoire.';
        } elseif (mb_strlen($lieu) < 3) {
            $errors['lieu'] = 'Le lieu doit contenir au moins 3 caracteres.';
        }
        if ($lieu !== null && mb_strlen($lieu) > 255) {
            $errors['lieu'] = 'Le lieu ne doit pas depasser 255 caracteres.';
        }

        $description = $offre->getDescription();
        if ($description === null || $description === '') {
            $errors['description'] = 'La description est obligatoire.';
        } elseif (mb_strlen($description) < 10) {
            $errors['description'] = 'La description doit contenir au moins 10 caracteres.';
        }
        if ($description !== null && mb_strlen($description) > 5000) {
            $errors['description'] = 'La description est trop longue (max 5000 caracteres).';
        }

        return $errors;
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function getCurrentAdminUserOption(EntityManagerInterface $entityManager, int $adminUserId): array
    {
        $rows = $entityManager->getConnection()->executeQuery(
            'SELECT id_utilisateur, nom, prenom, email FROM utilisateur WHERE id_utilisateur = :id LIMIT 1',
            ['id' => $adminUserId]
        )->fetchAllAssociative();

        $options = [];
        foreach ($rows as $row) {
            $id = (int) $row['id_utilisateur'];
            $nom = trim((string) ($row['nom'] ?? ''));
            $prenom = trim((string) ($row['prenom'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $name = trim($nom.' '.$prenom);
            $label = '#'.$id.' - '.($name !== '' ? $name : $email);

            $options[] = [
                'id' => $id,
                'label' => $label,
            ];
        }

        return $options;
    }

    private function requireAdminUserId(SessionInterface $session): ?int
    {
        $adminUserId = (int) $session->get('admin_user_id', 0);
        $adminRole = (string) $session->get('admin_user_role', '');

        if ($adminUserId <= 0 || !str_starts_with($adminRole, 'ADMIN')) {
            return null;
        }

        return $adminUserId;
    }

    private function userExists(EntityManagerInterface $entityManager, int $userId): bool
    {
        $result = $entityManager->getConnection()->executeQuery(
            'SELECT id_utilisateur FROM utilisateur WHERE id_utilisateur = :id LIMIT 1',
            ['id' => $userId]
        )->fetchOne();

        return $result !== false;
    }

    /**
     * @return list<string>
     */
    private function getDistinctOffreValues(EntityManagerInterface $entityManager, string $column): array
    {
        $allowedColumns = ['statut_offre', 'type_contrat', 'work_preference'];
        if (!in_array($column, $allowedColumns, true)) {
            return [];
        }

        $rows = $entityManager->getConnection()->executeQuery(
            sprintf("SELECT DISTINCT %s AS value FROM offre_emploi WHERE %s IS NOT NULL AND %s <> '' ORDER BY %s ASC", $column, $column, $column, $column)
        )->fetchFirstColumn();

        $values = array_map(static fn (mixed $value): string => (string) $value, $rows);

        if ($column === 'statut_offre') {
            $values = array_map(static fn (string $value): string => $value === 'ACTIVE' ? 'OUVERTE' : $value, $values);
            $values = array_values(array_unique($values));
        }

        return array_values($values);
    }

    /**
     * @return array<string, string>
     */
    private function validateCritereInput(
        ?string $niveauExperience,
        ?string $niveauEtude,
        ?string $competencesRequises,
        ?string $responsibilities,
    ): array {
        $errors = [];

        if ($niveauExperience === null || mb_strlen($niveauExperience) < 2 || mb_strlen($niveauExperience) > 50) {
            $errors['niveau_experience'] = 'Niveau experience obligatoire (2-50 caracteres).';
        }

        if ($niveauEtude === null || mb_strlen($niveauEtude) < 2 || mb_strlen($niveauEtude) > 50) {
            $errors['niveau_etude'] = 'Niveau etude obligatoire (2-50 caracteres).';
        }

        if ($competencesRequises === null || mb_strlen($competencesRequises) < 3 || mb_strlen($competencesRequises) > 2000) {
            $errors['competences_requises'] = 'Competences requises obligatoires (3-2000 caracteres).';
        }

        if ($responsibilities === null || mb_strlen($responsibilities) < 3 || mb_strlen($responsibilities) > 2000) {
            $errors['responsibilities'] = 'Responsibilities obligatoires (3-2000 caracteres).';
        }

        return $errors;
    }

    private function hydrateCritereFromValues(
        CritereOffre $critere,
        ?string $niveauExperience,
        ?string $niveauEtude,
        ?string $competencesRequises,
        ?string $responsibilities,
    ): void {
        $critere->setNiveauExperience($niveauExperience);
        $critere->setNiveauEtude($niveauEtude);
        $critere->setCompetencesRequises($competencesRequises);
        $critere->setResponsibilities($responsibilities);
    }
}
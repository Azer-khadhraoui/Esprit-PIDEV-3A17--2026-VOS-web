<?php

namespace App\Controller;

use App\Entity\OffreEmploi;
use App\Entity\PreferenceCandidature;
use App\Service\candidature\MatchingService;
use App\Service\OffreChatFilterAiService;
use App\Service\OffreTranslationAiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class ClientOffreController extends AbstractController
{
    private const COMPARE_SESSION_KEY = 'compare_offer_ids';
    private const ALERT_SESSION_KEY = 'temporary_offer_alert_filters';

    public function __construct(
        private readonly OffreTranslationAiService $offreTranslationAiService,
        private readonly OffreChatFilterAiService $offreChatFilterAiService,
    ) {
    }

    #[Route('/opportunites', name: 'client_opportunites', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $authScope = (string) $session->get('auth_scope', '');
        $hasClientSession = (bool) $session->get('user_id') && (string) $session->get('user_role', '') === 'CLIENT';
        $hasAdminSession = (bool) $session->get('admin_user_id') && str_starts_with((string) $session->get('admin_user_role', ''), 'ADMIN');

        $isClientAuthenticated = $authScope === 'client'
            ? $hasClientSession
            : ($authScope === '' ? $hasClientSession && !$hasAdminSession : false);

        $isAdminAuthenticated = $authScope === 'admin'
            ? $hasAdminSession
            : ($authScope === '' ? $hasAdminSession : false);

        if (!$isClientAuthenticated && !$isAdminAuthenticated) {
            return $this->redirectToRoute('app_signin');
        }

        $offreRepository = $entityManager->getRepository(OffreEmploi::class);
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 6;

        $search = $this->normalizeText($request->query->get('q'));
        $typeContrat = $this->normalizeText($request->query->get('type_contrat'));
        $workPreference = $this->normalizeText($request->query->get('work_preference'));
        $lieu = $this->normalizeText($request->query->get('lieu'));
        $statut = $this->normalizeText($request->query->get('statut'));
        $normalizedStatut = $statut !== null ? strtoupper($statut) : null;

        if ($normalizedStatut === 'ACTIVE') {
            $normalizedStatut = 'OUVERTE';
        }

        $qb = $offreRepository->createQueryBuilder('o');

        if ($search !== null) {
            $qb
                ->andWhere('o.titre LIKE :q OR o.description LIKE :q OR o.lieu LIKE :q')
                ->setParameter('q', '%'.$search.'%');
        }

        if ($typeContrat !== null) {
            $qb
                ->andWhere('o.typeContrat = :typeContrat')
                ->setParameter('typeContrat', $typeContrat);
        }

        if ($workPreference !== null) {
            $qb
                ->andWhere('o.workPreference = :workPreference')
                ->setParameter('workPreference', $workPreference);
        }

        if ($lieu !== null) {
            $qb
                ->andWhere('o.lieu LIKE :lieu')
                ->setParameter('lieu', '%'.$lieu.'%');
        }

        if ($normalizedStatut !== null) {
            if ($normalizedStatut === 'OUVERTE') {
                // Keep compatibility with legacy values saved as ACTIVE.
                $qb
                    ->andWhere('UPPER(COALESCE(o.statutOffre, \'\')) IN (:openStatuts)')
                    ->setParameter('openStatuts', ['OUVERTE', 'ACTIVE']);
            } else {
                $qb
                    ->andWhere('UPPER(COALESCE(o.statutOffre, \'\')) = :statut')
                    ->setParameter('statut', $normalizedStatut);
            }
        } else {
            $qb
                ->andWhere('UPPER(COALESCE(o.statutOffre, \'\')) IN (:openStatuts)')
                ->setParameter('openStatuts', ['OUVERTE', 'ACTIVE']);
        }

        $countQb = clone $qb;
        $totalOffers = (int) $countQb
            ->select('COUNT(o.idOffre)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalOffers / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $offers = $qb
            ->orderBy('o.datePublication', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $criteriaByOffer = $this->getLatestCriteriaByOffer($entityManager);
        $temporaryAlert = $this->getTemporaryAlertFromSession($session);
        $temporaryAlertMatches = $this->findTemporaryAlertMatches($entityManager, $temporaryAlert);
        $displayName = $isClientAuthenticated
            ? (string) $session->get('user_name', 'Utilisateur')
            : (string) $session->get('admin_user_name', 'Admin');

        return $this->render('client/opportunites.html.twig', [
            'offers' => $offers,
            'criteriaByOffer' => $criteriaByOffer,
            'compareOfferIds' => $this->getCompareOfferIdsFromSession($session),
            'temporaryAlert' => $temporaryAlert,
            'temporaryAlertMatches' => $temporaryAlertMatches,
            'isClientAuthenticated' => $isClientAuthenticated,
            'isAdminAuthenticated' => $isAdminAuthenticated,
            'userName' => $displayName,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $totalOffers,
                'totalPages' => $totalPages,
            ],
            'filters' => [
                'q' => $search,
                'type_contrat' => $typeContrat,
                'work_preference' => $workPreference,
                'lieu' => $lieu,
                'statut' => $normalizedStatut,
                'page' => $page,
            ],
            'filterOptions' => [
                // Static options keep UI stable and avoid inconsistent labels.
                'typesContrat' => ['CDI', 'CDD', 'Stage', 'Alternance', 'Freelance'],
                'workPreferences' => ['On-site', 'Remote', 'Hybrid'],
                'statuts' => ['OUVERTE', 'INACTIVE', 'FERMEE'],
            ],
        ]);
    }

    #[Route('/opportunites/mes-contrats/search', name: 'client_mes_contrats_search', methods: ['GET'])]
    public function mesContratsSearch(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): JsonResponse
    {
        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0) {
            return $this->json(['error' => 'Non autorisé'], 401);
        }

        $search    = trim((string) $request->query->get('search', ''));
        $type      = trim((string) $request->query->get('type', ''));
        $decision  = trim((string) $request->query->get('decision', ''));
        $dateFrom  = trim((string) $request->query->get('date_from', ''));
        $dateTo    = trim((string) $request->query->get('date_to', ''));

        $sql = "
            SELECT
                c.id_contrat, c.type_contrat, c.date_debut, c.date_fin,
                c.salaire, c.status, c.volume_horaire, c.avantages,
                c.periode, c.id_recrutement,
                r.date_decision, r.decision_finale
            FROM contrat_embauche c
            INNER JOIN recrutement r  ON r.id_recrutement = c.id_recrutement
            INNER JOIN entretien e    ON e.id_entretien   = r.id_entretien
            INNER JOIN candidature ca ON ca.id_candidature = e.id_candidature
            WHERE ca.id_utilisateur = :userId
        ";

        $params = ['userId' => $idUtilisateur];

        if ($search !== '') {
            $sql .= " AND (
                LOWER(c.type_contrat)       LIKE :q
                OR LOWER(c.status)          LIKE :q
                OR LOWER(r.decision_finale) LIKE :q
                OR LOWER(c.avantages)       LIKE :q
                OR LOWER(c.periode)         LIKE :q
            )";
            $params['q'] = '%' . strtolower($search) . '%';
        }

        if ($type !== '') {
            $sql .= " AND LOWER(c.type_contrat) = :type";
            $params['type'] = strtolower($type);
        }

        if ($decision !== '') {
            $decisionMap = [
                'accepte'  => ['accepté', 'accepte'],
                'refuse'   => ['refusé', 'refuse'],
                'attente'  => ['en attente', 'attente'],
            ];
            $variants = $decisionMap[$decision] ?? [$decision];
            $orParts = [];
            foreach ($variants as $i => $v) {
                $key = 'dec' . $i;
                $orParts[] = "LOWER(r.decision_finale) LIKE :$key";
                $params[$key] = '%' . $v . '%';
            }
            $sql .= ' AND (' . implode(' OR ', $orParts) . ')';
        }

        if ($dateFrom !== '') {
            $sql .= " AND c.date_debut >= :date_from";
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $sql .= " AND c.date_debut <= :date_to";
            $params['date_to'] = $dateTo;
        }

        $sql .= " ORDER BY c.date_debut DESC, c.id_contrat DESC";

        $contracts = $entityManager->getConnection()
            ->executeQuery($sql, $params)
            ->fetchAllAssociative();

        return $this->json(['total' => count($contracts), 'contracts' => $contracts]);
    }

    #[Route('/opportunites/mes-contrats', name: 'client_mes_contrats', methods: ['GET'])]
    public function mesContrats(EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0) {
            return $this->redirectToRoute('app_signin');
        }

        $contracts = $entityManager->getConnection()->executeQuery(
            "
                SELECT
                    c.id_contrat,
                    c.type_contrat,
                    c.date_debut,
                    c.date_fin,
                    c.salaire,
                    c.status,
                    c.volume_horaire,
                    c.avantages,
                    c.periode,
                    c.id_recrutement,
                    r.date_decision,
                    r.decision_finale
                FROM contrat_embauche c
                INNER JOIN recrutement r ON r.id_recrutement = c.id_recrutement
                INNER JOIN entretien e ON e.id_entretien = r.id_entretien
                INNER JOIN candidature ca ON ca.id_candidature = e.id_candidature
                WHERE ca.id_utilisateur = :userId
                ORDER BY c.date_debut DESC, c.id_contrat DESC
            ",
            ['userId' => $idUtilisateur]
        )->fetchAllAssociative();

        return $this->render('client/mes_contrats.html.twig', [
            'contracts' => $contracts,
            'userName' => (string) $session->get('user_name', 'Utilisateur'),
        ]);
    }

    #[Route('/opportunites/{idOffre}/postuler', name: 'client_postuler_offre', methods: ['POST'])]
    public function apply(
        #[MapEntity(id: 'idOffre')] OffreEmploi $offre,
        Request $request,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
    ): Response {
        $isClientAuthenticated = (bool) $session->get('user_id')
            && (string) $session->get('user_role', '') === 'CLIENT'
            && (string) $session->get('auth_scope', '') === 'client';

        if (!$isClientAuthenticated) {
            $this->addFlash('error', 'Action reservee aux clients connectes.');

            return $this->redirectToRoute('client_opportunites');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('postuler_offre_'.$offre->getIdOffre(), $token)) {
            $this->addFlash('error', 'Token invalide. Veuillez reessayer.');

            return $this->redirectToRoute('client_opportunites');
        }

        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0 || !$this->userExists($entityManager, $idUtilisateur)) {
            $this->addFlash('error', 'Session client invalide. Veuillez vous reconnecter.');

            return $this->redirectToRoute('client_opportunites');
        }

        $statutOffre = $offre->getStatutOffre();
        if ($statutOffre === 'FERMEE' || $statutOffre === 'INACTIVE') {
            $this->addFlash('error', 'Cette offre n est plus ouverte aux candidatures.');

            return $this->redirectToRoute('client_opportunites');
        }

        $message = $this->normalizeText($request->request->get('message_candidat')) ?? 'Candidature soumise via la page opportunites.';

        $entityManager->getConnection()->insert('candidature', [
            'date_candidature' => (new \DateTime())->format('Y-m-d'),
            'statut' => 'En attente',
            'message_candidat' => $message,
            'cv' => null,
            'lettre_motivation' => null,
            'niveau_experience' => null,
            'annees_experience' => null,
            'domaine_experience' => null,
            'dernier_poste' => null,
            'id_utilisateur' => $idUtilisateur,
            'id_offre' => $offre->getIdOffre(),
        ]);

        $this->addFlash('success', 'Candidature envoyee avec succes.');

        return $this->redirectToRoute('client_opportunites');
    }

    #[Route('/offres-compatibles', name: 'client_offres_compatibles', methods: ['GET'])]
    public function offresCompatibles(
        Request $request,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        MatchingService $matchingService
    ): Response {
        $isClientAuthenticated = (bool) $session->get('user_id')
            && (string) $session->get('user_role', '') === 'CLIENT'
            && (string) $session->get('auth_scope', '') === 'client';

        if (!$isClientAuthenticated) {
            return $this->redirectToRoute('app_signin');
        }

        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0) {
            return $this->redirectToRoute('app_signin');
        }

        // ─── Récupérer les préférences du candidat ─────────────────────────
        $preference = $entityManager->getRepository(PreferenceCandidature::class)
            ->findOneBy(['id_utilisateur' => $idUtilisateur]);

        // ─── Récupérer toutes les offres ouvertes ──────────────────────────
        $offres = $entityManager->getRepository(OffreEmploi::class)
            ->findBy(['statutOffre' => 'OUVERTE'], ['datePublication' => 'DESC']);

        if (empty($offres)) {
            // Aucune offre disponible dans le système
            return $this->render('client/candidature/offres_compatibles.html.twig', [
                'offresAvecScore' => [],
                'preference' => $preference,
                'userName' => $session->get('user_name', 'Candidat'),
                'activePage' => 'offres-compatibles',
                'pagination' => [
                    'page' => 1,
                    'totalPages' => 0,
                    'total' => 0,
                ],
                'message' => 'Aucune offre disponible pour le moment.',
            ]);
        }

        // ─── Calculer les scores de matching ────────────────────────────────
        $offresAvecScore = [];
        foreach ($offres as $offre) {
            $matching = $matchingService->calculateMatching($offre, $preference);
            
            // Inclure toutes les offres, même sans préférences
            // pour que l'utilisateur voie ce qui est disponible
            $offresAvecScore[] = [
                'offre' => $offre,
                'matching' => $matching,
            ];
        }

        // ─── Trier par score décroissant ───────────────────────────────────
        usort($offresAvecScore, function ($a, $b) {
            return $b['matching']['score'] <=> $a['matching']['score'];
        });

        // ─── Pagination ────────────────────────────────────────────────────
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;
        $total = count($offresAvecScore);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        
        $startIndex = ($page - 1) * $perPage;
        $paginatedOffres = array_slice($offresAvecScore, $startIndex, $perPage);

        return $this->render('client/candidature/offres_compatibles.html.twig', [
            'offresAvecScore' => $paginatedOffres,
            'preference' => $preference,
            'userName' => $session->get('user_name', 'Candidat'),
            'activePage' => 'offres-compatibles',
            'pagination' => [
                'page' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
            ],
        ]);
    }

    #[Route('/opportunites/translate-criteria', name: 'client_opportunites_translate_criteria', methods: ['GET'])]
    public function translateCriteria(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): JsonResponse
    {
        $authScope = (string) $session->get('auth_scope', '');
        $hasClientSession = (bool) $session->get('user_id') && (string) $session->get('user_role', '') === 'CLIENT';
        $hasAdminSession = (bool) $session->get('admin_user_id') && str_starts_with((string) $session->get('admin_user_role', ''), 'ADMIN');

        $isAuthenticated = $authScope === '' ? ($hasClientSession || $hasAdminSession) : (
            ($authScope === 'client' && $hasClientSession)
            || ($authScope === 'admin' && $hasAdminSession)
        );

        if (!$isAuthenticated) {
            return $this->json([
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $offerId = (int) $request->query->get('id_offre', 0);
        $targetLanguage = strtoupper((string) $request->query->get('lang', 'FR'));
        if ($offerId <= 0) {
            return $this->json([
                'message' => 'id_offre is required.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!in_array($targetLanguage, ['FR', 'EN'], true)) {
            return $this->json([
                'message' => 'lang must be FR or EN.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $criteria = $this->getLatestCriteriaForOfferId($entityManager, $offerId);
        if ($criteria === null) {
            return $this->json([
                'message' => 'Critere not found for this offer.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $niveauExperience = $this->offreTranslationAiService->translate((string) ($criteria['niveau_experience'] ?? ''), $targetLanguage);
        $niveauEtude = $this->offreTranslationAiService->translate((string) ($criteria['niveau_etude'] ?? ''), $targetLanguage);
        $competencesRequises = $this->offreTranslationAiService->translate((string) ($criteria['competences_requises'] ?? ''), $targetLanguage);
        $responsibilities = $this->offreTranslationAiService->translate((string) ($criteria['responsibilities'] ?? ''), $targetLanguage);

        return $this->json([
            'id_offre' => $offerId,
            'lang' => $targetLanguage,
            'niveau_experience' => $niveauExperience,
            'niveau_etude' => $niveauEtude,
            'competences_requises' => $competencesRequises,
            'responsibilities' => $responsibilities,
        ]);
    }

    #[Route('/opportunites/chatbot/suggest', name: 'client_opportunites_chatbot_suggest', methods: ['POST'])]
    public function chatbotSuggest(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): JsonResponse
    {
        $authScope = (string) $session->get('auth_scope', '');
        $hasClientSession = (bool) $session->get('user_id') && (string) $session->get('user_role', '') === 'CLIENT';
        $hasAdminSession = (bool) $session->get('admin_user_id') && str_starts_with((string) $session->get('admin_user_role', ''), 'ADMIN');

        $isAuthenticated = $authScope === '' ? ($hasClientSession || $hasAdminSession) : (
            ($authScope === 'client' && $hasClientSession)
            || ($authScope === 'admin' && $hasAdminSession)
        );

        if (!$isAuthenticated) {
            return $this->json([
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'message' => 'Invalid payload.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $message = $this->normalizeText($payload['message'] ?? null);
        if ($message === null) {
            return $this->json([
                'message' => 'message is required.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $availableOptions = [
            'typesContrat' => $this->getDistinctOffreValues($entityManager, 'type_contrat'),
            'workPreferences' => $this->getDistinctOffreValues($entityManager, 'work_preference'),
            'statuts' => $this->getDistinctOffreValues($entityManager, 'statut_offre'),
        ];

        $suggestion = $this->offreChatFilterAiService->suggestFilters($message, $availableOptions);
        $filters = $suggestion['filters'];

        $qb = $entityManager->getRepository(OffreEmploi::class)->createQueryBuilder('o');

        if ($filters['q'] !== null) {
            $qb
                ->andWhere('o.titre LIKE :q OR o.description LIKE :q OR o.lieu LIKE :q')
                ->setParameter('q', '%'.$filters['q'].'%');
        }

        if ($filters['type_contrat'] !== null) {
            $qb
                ->andWhere('o.typeContrat = :typeContrat')
                ->setParameter('typeContrat', $filters['type_contrat']);
        }

        if ($filters['work_preference'] !== null) {
            $qb
                ->andWhere('o.workPreference = :workPreference')
                ->setParameter('workPreference', $filters['work_preference']);
        }

        if ($filters['lieu'] !== null) {
            $qb
                ->andWhere('o.lieu LIKE :lieu')
                ->setParameter('lieu', '%'.$filters['lieu'].'%');
        }

        if ($filters['statut'] !== null) {
            $qb
                ->andWhere('o.statutOffre = :statut')
                ->setParameter('statut', $filters['statut']);
        } else {
            $qb
                ->andWhere('o.statutOffre IN (:openStatuts)')
                ->setParameter('openStatuts', ['OUVERTE']);
        }

        $offers = $qb
            ->orderBy('o.datePublication', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $recommendations = array_map(static function (OffreEmploi $offer): array {
            $description = (string) ($offer->getDescription() ?? '');
            $snippet = mb_substr($description, 0, 140);

            return [
                'idOffre' => (int) $offer->getIdOffre(),
                'titre' => (string) ($offer->getTitre() ?? 'Offre sans titre'),
                'typeContrat' => (string) ($offer->getTypeContrat() ?? 'N/A'),
                'workPreference' => (string) ($offer->getWorkPreference() ?? 'N/A'),
                'lieu' => (string) ($offer->getLieu() ?? 'N/A'),
                'statutOffre' => (string) ($offer->getStatutOffre() ?? 'N/A'),
                'datePublication' => $offer->getDatePublication()?->format('Y-m-d'),
                'descriptionSnippet' => $snippet.((mb_strlen($description) > 140) ? '...' : ''),
            ];
        }, $offers);

        $assistantMessage = $suggestion['reason'];
        if (count($recommendations) === 0) {
            $assistantMessage .= ' Je n ai pas trouve d offre exacte, essayez de preciser votre ville ou le type de contrat.';
        } else {
            $assistantMessage .= sprintf(' J ai trouve %d offre(s) qui correspondent.', count($recommendations));
        }

        return $this->json([
            'assistantMessage' => $assistantMessage,
            'appliedFilters' => $filters,
            'recommendations' => $recommendations,
        ]);
    }

    #[Route('/opportunites/compare/session/toggle', name: 'client_opportunites_compare_toggle', methods: ['POST'])]
    public function toggleCompareOffer(Request $request, SessionInterface $session): JsonResponse
    {
        if (!$this->isOpportunitesUserAuthenticated($session)) {
            return $this->json([
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'message' => 'Invalid payload.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $offerId = (int) ($payload['offerId'] ?? 0);
        if ($offerId <= 0) {
            return $this->json([
                'message' => 'offerId is required.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $selected = $this->getCompareOfferIdsFromSession($session);
        $index = array_search($offerId, $selected, true);

        if ($index !== false) {
            unset($selected[$index]);
            $selected = array_values($selected);
        } else {
            if (count($selected) >= 4) {
                return $this->json([
                    'message' => 'Vous pouvez comparer au maximum 4 offres par session.',
                    'selectedOfferIds' => $selected,
                    'count' => count($selected),
                ], JsonResponse::HTTP_BAD_REQUEST);
            }

            $selected[] = $offerId;
        }

        $session->set(self::COMPARE_SESSION_KEY, $selected);

        return $this->json([
            'selectedOfferIds' => $selected,
            'count' => count($selected),
        ]);
    }

    #[Route('/opportunites/compare/session', name: 'client_opportunites_compare_state', methods: ['GET'])]
    public function compareOfferState(SessionInterface $session): JsonResponse
    {
        if (!$this->isOpportunitesUserAuthenticated($session)) {
            return $this->json([
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $selected = $this->getCompareOfferIdsFromSession($session);

        return $this->json([
            'selectedOfferIds' => $selected,
            'count' => count($selected),
        ]);
    }

    #[Route('/opportunites/compare/session/clear', name: 'client_opportunites_compare_clear', methods: ['POST'])]
    public function clearCompareOffer(SessionInterface $session): JsonResponse
    {
        if (!$this->isOpportunitesUserAuthenticated($session)) {
            return $this->json([
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $session->remove(self::COMPARE_SESSION_KEY);

        return $this->json([
            'selectedOfferIds' => [],
            'count' => 0,
        ]);
    }

    #[Route('/opportunites/compare/session/preview', name: 'client_opportunites_compare_preview', methods: ['POST'])]
    public function compareOfferPreview(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): JsonResponse
    {
        if (!$this->isOpportunitesUserAuthenticated($session)) {
            return $this->json([
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $selected = is_array($payload)
            ? $this->normalizeCompareOfferIds($payload['selectedOfferIds'] ?? null)
            : [];

        if (count($selected) === 0) {
            $selected = $this->getCompareOfferIdsFromSession($session);
        }

        if (count($selected) < 2 || count($selected) > 4) {
            return $this->json([
                'message' => 'Selection invalide. Choisissez entre 2 et 4 offres.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $offers = $entityManager->getRepository(OffreEmploi::class)->findBy(['idOffre' => $selected]);
        $offersById = [];
        foreach ($offers as $offer) {
            $offersById[(int) $offer->getIdOffre()] = $offer;
        }

        $criteriaByOffer = $this->getLatestCriteriaByOffer($entityManager);
        $comparisonOffers = [];
        foreach ($selected as $offerId) {
            if (!isset($offersById[$offerId])) {
                continue;
            }

            $offer = $offersById[$offerId];
            $criteria = $criteriaByOffer[$offerId] ?? null;
            $skills = [];
            if (is_array($criteria) && isset($criteria['competences_requises']) && is_string($criteria['competences_requises'])) {
                $lines = preg_split('/\r\n|\r|\n/', $criteria['competences_requises']) ?: [];
                $skills = array_values(array_filter(array_map(static fn (string $line): string => trim(preg_replace('/^[-*]\s*/', '', $line) ?? $line), $lines), static fn (string $line): bool => $line !== ''));
            }

            $comparisonOffers[] = [
                'idOffre' => (int) $offer->getIdOffre(),
                'titre' => (string) ($offer->getTitre() ?? 'Offre'),
                'typeContrat' => (string) ($offer->getTypeContrat() ?? 'N/A'),
                'workPreference' => (string) ($offer->getWorkPreference() ?? 'N/A'),
                'lieu' => (string) ($offer->getLieu() ?? 'N/A'),
                'competencesRequises' => $skills,
            ];
        }

        return $this->json([
            'offers' => $comparisonOffers,
            'count' => count($comparisonOffers),
        ]);
    }

    #[Route('/opportunites/alert/session/preview', name: 'client_opportunites_alert_preview', methods: ['POST'])]
    public function previewTemporaryAlert(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): JsonResponse
    {
        if (!$this->isOpportunitesUserAuthenticated($session)) {
            return $this->json([
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'message' => 'Invalid payload.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $alert = $this->normalizeTemporaryAlertFilters($payload);
        if (!$this->hasTemporaryAlertFilterValue($alert)) {
            return $this->json([
                'alert' => null,
                'matches' => [
                    'count' => 0,
                    'offers' => [],
                ],
            ]);
        }

        return $this->json([
            'alert' => $alert,
            'matches' => $this->findTemporaryAlertMatches($entityManager, $alert),
        ]);
    }

    #[Route('/opportunites/alert/session/save', name: 'client_opportunites_alert_save', methods: ['POST'])]
    public function saveTemporaryAlert(Request $request, SessionInterface $session): JsonResponse
    {
        if (!$this->isOpportunitesUserAuthenticated($session)) {
            return $this->json([
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'message' => 'Invalid payload.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $alert = $this->normalizeTemporaryAlertFilters($payload);
        if (!$this->hasTemporaryAlertFilterValue($alert)) {
            return $this->json([
                'message' => 'Veuillez renseigner au moins un critere pour activer une alerte temporaire.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $session->set(self::ALERT_SESSION_KEY, $alert);

        return $this->json([
            'alert' => $alert,
            'message' => 'Alerte temporaire activee pour cette session.',
        ]);
    }

    #[Route('/opportunites/alert/session', name: 'client_opportunites_alert_state', methods: ['GET'])]
    public function getTemporaryAlert(SessionInterface $session): JsonResponse
    {
        if (!$this->isOpportunitesUserAuthenticated($session)) {
            return $this->json([
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $alert = $this->getTemporaryAlertFromSession($session);

        return $this->json([
            'alert' => $alert,
            'active' => $alert !== null,
        ]);
    }

    #[Route('/opportunites/alert/session/clear', name: 'client_opportunites_alert_clear', methods: ['POST'])]
    public function clearTemporaryAlert(SessionInterface $session): JsonResponse
    {
        if (!$this->isOpportunitesUserAuthenticated($session)) {
            return $this->json([
                'message' => 'Unauthorized',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $session->remove(self::ALERT_SESSION_KEY);

        return $this->json([
            'alert' => null,
            'message' => 'Alerte temporaire supprimee.',
        ]);
    }

    #[Route('/opportunites/{idOffre}/matching-score', name: 'client_offre_matching_score_json', methods: ['GET'])]
    public function getMatchingScoreJson(
        #[MapEntity(id: 'idOffre')] OffreEmploi $offre,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        MatchingService $matchingService
    ): JsonResponse {
        $isClientAuthenticated = (bool) $session->get('user_id')
            && (string) $session->get('user_role', '') === 'CLIENT'
            && (string) $session->get('auth_scope', '') === 'client';

        if (!$isClientAuthenticated) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        // Récupérer les préférences du candidat
        $preference = $entityManager->getRepository(PreferenceCandidature::class)
            ->findOneBy(['id_utilisateur' => $idUtilisateur]);

        // Calculer le matching
        $matching = $matchingService->calculateMatching($offre, $preference);

        return new JsonResponse($matching);
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
     * @return array{q:?string, type_contrat:?string, work_preference:?string, lieu:?string, statut:?string}|null
     */
    private function getTemporaryAlertFromSession(SessionInterface $session): ?array
    {
        $raw = $session->get(self::ALERT_SESSION_KEY);
        if (!is_array($raw)) {
            return null;
        }

        $alert = $this->normalizeTemporaryAlertFilters($raw);

        return $this->hasTemporaryAlertFilterValue($alert) ? $alert : null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{q:?string, type_contrat:?string, work_preference:?string, lieu:?string, statut:?string}
     */
    private function normalizeTemporaryAlertFilters(array $payload): array
    {
        $statut = $this->normalizeText($payload['statut'] ?? null);
        if ($statut === 'ACTIVE') {
            $statut = 'OUVERTE';
        }

        return [
            'q' => $this->normalizeText($payload['q'] ?? null),
            'type_contrat' => $this->normalizeText($payload['type_contrat'] ?? null),
            'work_preference' => $this->normalizeText($payload['work_preference'] ?? null),
            'lieu' => $this->normalizeText($payload['lieu'] ?? null),
            'statut' => $statut,
        ];
    }

    /**
     * @param array{q:?string, type_contrat:?string, work_preference:?string, lieu:?string, statut:?string}|null $alert
     */
    private function hasTemporaryAlertFilterValue(?array $alert): bool
    {
        if ($alert === null) {
            return false;
        }

        return $alert['q'] !== null
            || $alert['type_contrat'] !== null
            || $alert['work_preference'] !== null
            || $alert['lieu'] !== null
            || $alert['statut'] !== null;
    }

    /**
     * @param array{q:?string, type_contrat:?string, work_preference:?string, lieu:?string, statut:?string}|null $alert
     *
     * @return array{count:int, offers:list<array{idOffre:int, titre:string, typeContrat:string, workPreference:string, lieu:string}>}
     */
    private function findTemporaryAlertMatches(EntityManagerInterface $entityManager, ?array $alert): array
    {
        if ($alert === null) {
            return [
                'count' => 0,
                'offers' => [],
            ];
        }

        $qb = $entityManager->getRepository(OffreEmploi::class)->createQueryBuilder('o');

        if ($alert['q'] !== null) {
            $qb
                ->andWhere('o.titre LIKE :q OR o.description LIKE :q OR o.lieu LIKE :q')
                ->setParameter('q', '%'.$alert['q'].'%');
        }

        if ($alert['type_contrat'] !== null) {
            $qb
                ->andWhere('o.typeContrat = :typeContrat')
                ->setParameter('typeContrat', $alert['type_contrat']);
        }

        if ($alert['work_preference'] !== null) {
            $qb
                ->andWhere('o.workPreference = :workPreference')
                ->setParameter('workPreference', $alert['work_preference']);
        }

        if ($alert['lieu'] !== null) {
            $qb
                ->andWhere('o.lieu LIKE :lieu')
                ->setParameter('lieu', '%'.$alert['lieu'].'%');
        }

        if ($alert['statut'] !== null) {
            $qb
                ->andWhere('o.statutOffre = :statut')
                ->setParameter('statut', $alert['statut']);
        } else {
            $qb
                ->andWhere('o.statutOffre IN (:openStatuts)')
                ->setParameter('openStatuts', ['OUVERTE']);
        }

        $offers = $qb
            ->orderBy('o.datePublication', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        $normalizedOffers = array_map(static function (OffreEmploi $offer): array {
            return [
                'idOffre' => (int) $offer->getIdOffre(),
                'titre' => (string) ($offer->getTitre() ?? 'Offre sans titre'),
                'typeContrat' => (string) ($offer->getTypeContrat() ?? 'N/A'),
                'workPreference' => (string) ($offer->getWorkPreference() ?? 'N/A'),
                'lieu' => (string) ($offer->getLieu() ?? 'N/A'),
            ];
        }, $offers);

        return [
            'count' => count($normalizedOffers),
            'offers' => $normalizedOffers,
        ];
    }

    private function isOpportunitesUserAuthenticated(SessionInterface $session): bool
    {
        $authScope = (string) $session->get('auth_scope', '');
        $hasClientSession = (bool) $session->get('user_id') && (string) $session->get('user_role', '') === 'CLIENT';
        $hasAdminSession = (bool) $session->get('admin_user_id') && str_starts_with((string) $session->get('admin_user_role', ''), 'ADMIN');

        return $authScope === '' ? ($hasClientSession || $hasAdminSession) : (
            ($authScope === 'client' && $hasClientSession)
            || ($authScope === 'admin' && $hasAdminSession)
        );
    }

    /**
     * @return list<int>
     */
    private function getCompareOfferIdsFromSession(SessionInterface $session): array
    {
        $raw = $session->get(self::COMPARE_SESSION_KEY, []);
        return $this->normalizeCompareOfferIds($raw);
    }

    /**
     * @return list<int>
     */
    private function normalizeCompareOfferIds(mixed $value): array
    {
        $raw = is_array($value) ? $value : [];
        $normalized = array_values(array_unique(array_filter(array_map(static fn (mixed $value): int => (int) $value, $raw), static fn (int $value): bool => $value > 0)));

        return array_slice($normalized, 0, 4);
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

    private function userExists(EntityManagerInterface $entityManager, int $userId): bool
    {
        $result = $entityManager->getConnection()->executeQuery(
            'SELECT id_utilisateur FROM utilisateur WHERE id_utilisateur = :id LIMIT 1',
            ['id' => $userId]
        )->fetchOne();

        return $result !== false;
    }

    /**
     * @return array<int, array{niveau_experience: ?string, niveau_etude: ?string, competences_requises: ?string, responsibilities: ?string}>
     */
    private function getLatestCriteriaByOffer(EntityManagerInterface $entityManager): array
    {
        $rows = $entityManager->getConnection()->executeQuery(
            'SELECT id_offre, id_critere, niveau_experience, niveau_etude, competences_requises, responsibilities FROM critere_offre ORDER BY id_offre ASC, id_critere DESC'
        )->fetchAllAssociative();

        $criteriaByOffer = [];
        foreach ($rows as $row) {
            $offreId = (int) $row['id_offre'];
            if (isset($criteriaByOffer[$offreId])) {
                continue;
            }

            $criteriaByOffer[$offreId] = [
                'niveau_experience' => isset($row['niveau_experience']) ? (string) $row['niveau_experience'] : null,
                'niveau_etude' => isset($row['niveau_etude']) ? (string) $row['niveau_etude'] : null,
                'competences_requises' => isset($row['competences_requises']) ? (string) $row['competences_requises'] : null,
                'responsibilities' => isset($row['responsibilities']) ? (string) $row['responsibilities'] : null,
            ];
        }

        return $criteriaByOffer;
    }

    /**
     * @return array{niveau_experience: ?string, niveau_etude: ?string, competences_requises: ?string, responsibilities: ?string}|null
     */
    private function getLatestCriteriaForOfferId(EntityManagerInterface $entityManager, int $offerId): ?array
    {
        $row = $entityManager->getConnection()->executeQuery(
            'SELECT niveau_experience, niveau_etude, competences_requises, responsibilities FROM critere_offre WHERE id_offre = :offerId ORDER BY id_critere DESC LIMIT 1',
            ['offerId' => $offerId]
        )->fetchAssociative();

        if (!is_array($row)) {
            return null;
        }

        return [
            'niveau_experience' => isset($row['niveau_experience']) ? (string) $row['niveau_experience'] : null,
            'niveau_etude' => isset($row['niveau_etude']) ? (string) $row['niveau_etude'] : null,
            'competences_requises' => isset($row['competences_requises']) ? (string) $row['competences_requises'] : null,
            'responsibilities' => isset($row['responsibilities']) ? (string) $row['responsibilities'] : null,
        ];
    }
}
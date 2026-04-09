<?php

namespace App\Controller;

use App\Entity\CritereOffre;
use App\Entity\OffreEmploi;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GestionOffreController extends AbstractController
{
    #[Route('/gestion-offre', name: 'gestion_offre_dashboard', methods: ['GET'])]
    public function dashboard(Request $request, EntityManagerInterface $entityManager): Response
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
                'q' => $search,
                'statut' => $statutFilter,
                'type_contrat' => $typeContratFilter,
                'work_preference' => $workPreferenceFilter,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
            'filterOptions' => [
                'statuts' => $this->getDistinctOffreValues($entityManager, 'statut_offre'),
                'typesContrat' => $this->getDistinctOffreValues($entityManager, 'type_contrat'),
                'workPreferences' => $this->getDistinctOffreValues($entityManager, 'work_preference'),
            ],
            'currentUserName' => 'khadhraoui azer',
        ]);
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
    public function newOffre(Request $request, EntityManagerInterface $entityManager): Response
    {
        $offre = new OffreEmploi();
        $userOptions = $this->getUserOptions($entityManager);

        if ($request->isMethod('POST')) {
            $this->hydrateOffreFromRequest($offre, $request);
            $errors = $this->validateOffreInput($offre, $entityManager);

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                return $this->render('gestion_offre/offre_form.html.twig', [
                    'offre' => $offre,
                    'pageTitle' => 'Ajouter Offre',
                    'submitLabel' => 'Ajouter',
                    'userOptions' => $userOptions,
                    'currentUserName' => 'khadhraoui azer',
                ]);
            }

            $entityManager->persist($offre);
            $entityManager->flush();

            $this->addFlash('success', 'Offre ajoutee avec succes.');

            return $this->redirectToRoute('gestion_offre_dashboard');
        }

        return $this->render('gestion_offre/offre_form.html.twig', [
            'offre' => $offre,
            'pageTitle' => 'Ajouter Offre',
            'submitLabel' => 'Ajouter',
            'userOptions' => $userOptions,
            'currentUserName' => 'khadhraoui azer',
        ]);
    }

    #[Route('/gestion-offre/{idOffre}/edit', name: 'gestion_offre_edit', methods: ['GET', 'POST'])]
    public function editOffre(
        #[MapEntity(id: 'idOffre')] OffreEmploi $offre,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $userOptions = $this->getUserOptions($entityManager);

        if ($request->isMethod('POST')) {
            $this->hydrateOffreFromRequest($offre, $request);
            $errors = $this->validateOffreInput($offre, $entityManager);

            if ($errors !== []) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                return $this->render('gestion_offre/offre_form.html.twig', [
                    'offre' => $offre,
                    'pageTitle' => 'Modifier Offre',
                    'submitLabel' => 'Mettre a jour',
                    'userOptions' => $userOptions,
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
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }

            if ($request->request->get('context') === 'offer') {
                return $this->redirectToRoute('gestion_offre_criteres', ['idOffre' => $offre->getIdOffre()]);
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
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

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
            'currentUserName' => 'khadhraoui azer',
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

    private function hydrateOffreFromRequest(OffreEmploi $offre, Request $request): void
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

        $idUtilisateurInput = $this->normalizeText($request->request->get('id_utilisateur'));
        $idUtilisateur = $idUtilisateurInput !== null ? (int) $idUtilisateurInput : null;

        $offre
            ->setTitre($this->normalizeText($request->request->get('titre')))
            ->setDescription($this->normalizeText($request->request->get('description')))
            ->setTypeContrat($this->normalizeText($request->request->get('type_contrat')))
            ->setStatutOffre($this->normalizeText($request->request->get('statut_offre')))
            ->setDatePublication($datePublication)
            ->setIdUtilisateur($idUtilisateur)
            ->setWorkPreference($this->normalizeText($request->request->get('work_preference')))
            ->setLieu($this->normalizeText($request->request->get('lieu')));
    }

    /**
     * @return list<string>
     */
    private function validateOffreInput(OffreEmploi $offre, EntityManagerInterface $entityManager): array
    {
        $errors = [];

        $titre = $offre->getTitre();
        if ($titre === null || mb_strlen($titre) < 3) {
            $errors[] = 'Le titre est obligatoire et doit contenir au moins 3 caracteres.';
        }

        $allowedTypeContrat = ['CDI', 'CDD', 'Stage', 'Alternance', 'Freelance'];
        $typeContrat = $offre->getTypeContrat();
        if ($typeContrat === null || !in_array($typeContrat, $allowedTypeContrat, true)) {
            $errors[] = 'Type contrat invalide. Choisissez une valeur de la liste.';
        }

        $allowedStatut = ['OUVERTE', 'FERMEE', 'INACTIVE'];
        $statut = $offre->getStatutOffre();
        if ($statut === null || !in_array($statut, $allowedStatut, true)) {
            $errors[] = 'Statut invalide. Choisissez une valeur de la liste.';
        }

        $allowedWorkPreference = ['On-site', 'Remote', 'Hybrid'];
        $workPreference = $offre->getWorkPreference();
        if ($workPreference === null || !in_array($workPreference, $allowedWorkPreference, true)) {
            $errors[] = 'Work preference invalide. Choisissez une valeur de la liste.';
        }

        if ($offre->getDatePublication() === null) {
            $errors[] = 'La date de publication est obligatoire.';
        }

        $idUtilisateur = $offre->getIdUtilisateur();
        if ($idUtilisateur === null || !$this->userExists($entityManager, $idUtilisateur)) {
            $errors[] = 'Veuillez choisir un utilisateur existant.';
        }

        $lieu = $offre->getLieu();
        if ($lieu !== null && mb_strlen($lieu) > 255) {
            $errors[] = 'Le lieu ne doit pas depasser 255 caracteres.';
        }

        $description = $offre->getDescription();
        if ($description !== null && mb_strlen($description) > 5000) {
            $errors[] = 'La description est trop longue (max 5000 caracteres).';
        }

        return $errors;
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function getUserOptions(EntityManagerInterface $entityManager): array
    {
        $rows = $entityManager->getConnection()->executeQuery(
            'SELECT id_utilisateur, nom, prenom, email FROM utilisateur ORDER BY id_utilisateur ASC'
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
     * @return list<string>
     */
    private function validateCritereInput(
        ?string $niveauExperience,
        ?string $niveauEtude,
        ?string $competencesRequises,
        ?string $responsibilities,
    ): array {
        $errors = [];

        if ($niveauExperience === null || mb_strlen($niveauExperience) < 2 || mb_strlen($niveauExperience) > 50) {
            $errors[] = 'Niveau experience obligatoire (2-50 caracteres).';
        }

        if ($niveauEtude === null || mb_strlen($niveauEtude) < 2 || mb_strlen($niveauEtude) > 50) {
            $errors[] = 'Niveau etude obligatoire (2-50 caracteres).';
        }

        if ($competencesRequises === null || mb_strlen($competencesRequises) < 3 || mb_strlen($competencesRequises) > 2000) {
            $errors[] = 'Competences requises obligatoires (3-2000 caracteres).';
        }

        if ($responsibilities === null || mb_strlen($responsibilities) < 3 || mb_strlen($responsibilities) > 2000) {
            $errors[] = 'Responsibilities obligatoires (3-2000 caracteres).';
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
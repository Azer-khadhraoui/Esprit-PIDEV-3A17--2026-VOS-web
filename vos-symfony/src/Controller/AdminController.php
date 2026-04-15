<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\OffreEmploi;
use App\Entity\PreferenceCandidature;
use App\Entity\User;
use App\Form\AdminUserType;
use App\Service\AdminDashboardService;
use App\Service\AdminUserService;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(AdminDashboardService $dashboardService, SessionInterface $session, Request $request): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $search = (string) $request->query->get('search', '');
        $sortBy = (string) $request->query->get('sortBy', 'id');
        $sortOrder = (string) $request->query->get('sortOrder', 'DESC');
        $roleFilter = (string) $request->query->get('role', '');

        $users = $dashboardService->getUsers($search, $sortBy, $sortOrder, $roleFilter);
        $stats = $dashboardService->getStats();

        return $this->render('admin/dashboard.html.twig', [
            'users' => $users,
            'stats' => $stats,
            'adminName' => (string) $session->get('admin_user_name', 'Admin'),
            'adminUserId' => (int) $session->get('admin_user_id', 0),
            'search' => $search,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'roleFilter' => $roleFilter,
        ]);
    }

    #[Route('/users/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(User $user, Request $request, AdminUserService $adminUserService, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $form = $this->createForm(AdminUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $uploadedImage = $form->get('imageFile')->getData();

                $adminUserService->updateUser(
                    $user,
                    (string) $user->getNom(),
                    (string) $user->getPrenom(),
                    (string) $user->getEmail(),
                    (string) $user->getRole(),
                    $uploadedImage
                );
                $this->addFlash('success', 'Utilisateur modifié avec succès.');
                return $this->redirectToRoute('app_admin_dashboard');
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('admin/edit_user.html.twig', [
            'editForm' => $form->createView(),
            'user' => $user,
            'adminName' => (string) $session->get('admin_user_name', 'Admin'),
        ]);
    }

    #[Route('/users/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(User $user, Request $request, AdminUserService $adminUserService, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        try {
            $adminUserService->deleteUser($user, (int) $session->get('admin_user_id'));
            $this->addFlash('success', 'Utilisateur supprimé avec succès.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/users/statistique', name: 'app_admin_users_statistique', methods: ['GET'])]
    public function usersStatistique(AdminDashboardService $dashboardService, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $stats = $dashboardService->getStats();
        $roleStats = $dashboardService->getRoleStats();

        return $this->render('admin/users_statistique.html.twig', [
            'stats' => $stats,
            'roleStats' => $roleStats,
            'adminName' => (string) $session->get('admin_user_name', 'Admin'),
        ]);
    }

     #[Route('/candidatures', name: 'app_admin_candidatures', methods: ['GET'])]
    public function candidatures(EntityManagerInterface $entityManager, SessionInterface $session, Request $request): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $search = trim((string) $request->query->get('search', ''));
        $statusFilter = trim((string) $request->query->get('status', ''));
        $sortBy = (string) $request->query->get('sortBy', 'id_candidature');
        $sortOrder = strtoupper((string) $request->query->get('sortOrder', 'DESC'));

        $allowedSortBy = ['id_candidature', 'date_candidature', 'statut'];
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'id_candidature';
        }

        if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
            $sortOrder = 'DESC';
        }

        $qb = $entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin(User::class, 'u', 'WITH', 'u.id = c.id_utilisateur')
            ->leftJoin(OffreEmploi::class, 'o', 'WITH', 'o.idOffre = c.id_offre');

        if ('' !== $search) {
            $searchExpr = $qb->expr()->orX(
                'LOWER(c.statut) LIKE :search',
                'LOWER(c.message_candidat) LIKE :search',
                'LOWER(u.nom) LIKE :search',
                'LOWER(u.prenom) LIKE :search',
                'LOWER(o.titre) LIKE :search'
            );

            if (ctype_digit($search)) {
                $searchExpr->add('c.id_candidature = :searchId');
                $qb->setParameter('searchId', (int) $search);
            }

            $qb
                ->andWhere($searchExpr)
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        if ('' !== $statusFilter) {
            $qb
                ->andWhere('c.statut = :status')
                ->setParameter('status', $statusFilter);
        }

        $qb->orderBy('c.'.$sortBy, $sortOrder);
        
        $candidatures = $qb->getQuery()->getResult();
        
        // Charger les User et OffreEmploi séparément
        $userIds = [];
        $offreIds = [];
        
        foreach ($candidatures as $candidature) {
            if ($candidature->getIdUtilisateur()) {
                $userIds[$candidature->getIdUtilisateur()] = true;
            }
            if ($candidature->getIdOffre()) {
                $offreIds[$candidature->getIdOffre()] = true;
            }
        }
        
        $users = [];
        $offres = [];
        
        if (!empty($userIds)) {
            $userObjs = $entityManager->getRepository(User::class)->findBy(['id' => array_keys($userIds)]);
            foreach ($userObjs as $user) {
                $users[$user->getId()] = $user;
            }
        }
        
        if (!empty($offreIds)) {
            $offreObjs = $entityManager->getRepository(OffreEmploi::class)->findBy(['idOffre' => array_keys($offreIds)]);
            foreach ($offreObjs as $offre) {
                $offres[$offre->getIdOffre()] = $offre;
            }
        }

        return $this->render('admin/candidature/index.html.twig', [
            'candidatures' => $candidatures,
            'adminName' => (string) $session->get('admin_user_name', 'Admin'),
            'search' => $search,
            'statusFilter' => $statusFilter,
            'statusOptions' => ['En attente', 'En examens', 'Accepté', 'Refusé'],
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'users' => $users,
            'offres' => $offres,
        ]);
    }

    #[Route('/candidatures/{id}/edit', name: 'app_admin_candidature_edit', methods: ['GET', 'POST'])]
    public function editCandidature(Candidature $candidature, Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $fieldErrors = [];

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('edit_candidature_' . $candidature->getIdCandidature(), $token)) {
                $fieldErrors['statut'] = 'Token CSRF invalide.';
            }

            $allowedStatus = ['En attente', 'En examens', 'Accepté', 'Refusé'];
            $statut = trim((string) $request->request->get('statut', ''));
            if ($statut === '' || !in_array($statut, $allowedStatus, true)) {
                $fieldErrors['statut'] = 'Statut invalide.';
            }

            if ($fieldErrors === []) {
                $candidature->setStatut($statut);
                $entityManager->flush();
                $this->addFlash('success', 'Candidature modifiée avec succès.');

                return $this->redirectToRoute('app_admin_candidatures');
            }

            if ($statut !== '') {
                $candidature->setStatut($statut);
            }
        }

        // Charger l'utilisateur et l'offre séparément
        $user = null;
        $offre = null;
        
        if ($candidature->getIdUtilisateur()) {
            $user = $entityManager->getRepository(User::class)->find($candidature->getIdUtilisateur());
        }
        
        if ($candidature->getIdOffre()) {
            $offre = $entityManager->getRepository(OffreEmploi::class)->find($candidature->getIdOffre());
        }

        return $this->render('admin/candidature/edit.html.twig', [
            'candidature' => $candidature,
            'adminName' => (string) $session->get('admin_user_name', 'Admin'),
            'user' => $user,
            'offre' => $offre,
            'fieldErrors' => $fieldErrors,
        ]);
    }

    #[Route('/candidatures/{id}/delete', name: 'app_admin_candidature_delete', methods: ['POST'])]
    public function deleteCandidature(Candidature $candidature, Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        if (!$this->isCsrfTokenValid('delete_candidature_' . $candidature->getIdCandidature(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_candidatures');
        }

        try {
            $entityManager->remove($candidature);
            $entityManager->flush();
            $this->addFlash('success', 'Candidature supprimée avec succès.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Erreur lors de la suppression.');
        }

        return $this->redirectToRoute('app_admin_candidatures');
    }

    // ── Export PDF - Liste des candidatures ───────────────────────────
    #[Route('/candidatures/export-pdf', name: 'app_admin_candidatures_export_pdf', methods: ['GET'])]
    public function exportCandidaturesPdf(
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        Request $request,
        PdfService $pdfService
    ): Response {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $search = trim((string) $request->query->get('search', ''));
        $statusFilter = trim((string) $request->query->get('status', ''));
        $sortBy = (string) $request->query->get('sortBy', 'id_candidature');
        $sortOrder = strtoupper((string) $request->query->get('sortOrder', 'DESC'));

        $allowedSortBy = ['id_candidature', 'date_candidature', 'statut'];
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'id_candidature';
        }

        if (!in_array($sortOrder, ['ASC', 'DESC'], true)) {
            $sortOrder = 'DESC';
        }

        $qb = $entityManager->getRepository(Candidature::class)
            ->createQueryBuilder('c')
            ->leftJoin(User::class, 'u', 'WITH', 'u.id = c.id_utilisateur')
            ->leftJoin(OffreEmploi::class, 'o', 'WITH', 'o.idOffre = c.id_offre');

        if ('' !== $search) {
            $searchExpr = $qb->expr()->orX(
                $qb->expr()->like('u.nom', ':search'),
                $qb->expr()->like('u.prenom', ':search'),
                $qb->expr()->like('o.titre', ':search')
            );

            if (ctype_digit($search)) {
                $searchExpr = $qb->expr()->orX(
                    $searchExpr,
                    $qb->expr()->eq('c.id_candidature', ':id')
                );
                $qb->setParameter('id', $search);
            }

            $qb->andWhere($searchExpr)->setParameter('search', '%' . $search . '%');
        }

        if ('' !== $statusFilter) {
            $qb->andWhere('c.statut = :status')->setParameter('status', $statusFilter);
        }

        $qb->orderBy('c.'.$sortBy, $sortOrder);
        
        $candidatures = $qb->getQuery()->getResult();
        
        // Charger les User et OffreEmploi séparément
        $userIds = [];
        $offreIds = [];
        
        foreach ($candidatures as $candidature) {
            if ($candidature->getIdUtilisateur()) {
                $userIds[$candidature->getIdUtilisateur()] = true;
            }
            if ($candidature->getIdOffre()) {
                $offreIds[$candidature->getIdOffre()] = true;
            }
        }
        
        $users = [];
        $offres = [];
        
        if (!empty($userIds)) {
            $userObjs = $entityManager->getRepository(User::class)->findBy(['id' => array_keys($userIds)]);
            foreach ($userObjs as $user) {
                $users[$user->getId()] = $user;
            }
        }
        
        if (!empty($offreIds)) {
            $offreObjs = $entityManager->getRepository(OffreEmploi::class)->findBy(['idOffre' => array_keys($offreIds)]);
            foreach ($offreObjs as $offre) {
                $offres[$offre->getIdOffre()] = $offre;
            }
        }

        $html = $this->renderView('pdf/candidatures_list_admin.html.twig', [
            'candidatures' => $candidatures,
            'users' => $users,
            'offres' => $offres,
        ]);

        $filename = 'candidatures_admin_' . date('d-m-Y') . '.pdf';

        return $pdfService->generatePdfResponse($html, $filename);
    }

    // ── Export PDF - Détail d'une candidature ────────────────────────
    #[Route('/candidatures/{id}/export-pdf', name: 'app_admin_candidature_detail_pdf', methods: ['GET'])]
    public function exportCandidatureDetailPdf(
        Candidature $candidature,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        PdfService $pdfService
    ): Response {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $candidat = $entityManager->getRepository(User::class)->find($candidature->getIdUtilisateur());
        $offre = $entityManager->getRepository(OffreEmploi::class)->find($candidature->getIdOffre());

        $html = $this->renderView('pdf/candidature_detail.html.twig', [
            'candidature' => $candidature,
            'candidat' => $candidat,
            'offre' => $offre,
        ]);

        $filename = 'candidature_' . $candidature->getIdCandidature() . '_' . date('d-m-Y') . '.pdf';

        return $pdfService->generatePdfResponse($html, $filename);
    }

    #[Route('/preferences', name: 'app_admin_preferences', methods: ['GET'])]
    public function preferences(EntityManagerInterface $entityManager, SessionInterface $session, Request $request): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $search = (string) $request->query->get('search', '');
        $sortBy = (string) $request->query->get('sortBy', 'id_preference');
        $sortOrder = (string) $request->query->get('sortOrder', 'DESC');

        $allowedSortFields = [
            'id_preference' => 'p.id_preference',
            'type_poste_souhaite' => 'p.type_poste_souhaite',
            'mode_travail' => 'p.mode_travail',
            'disponibilite' => 'p.disponibilite',
            'date_disponibilite' => 'p.date_disponibilite',
        ];

        $sortBy = array_key_exists($sortBy, $allowedSortFields) ? $sortBy : 'id_preference';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $entityManager->getRepository(PreferenceCandidature::class)
            ->createQueryBuilder('p')
            ->leftJoin(User::class, 'u', 'WITH', 'u.id = p.id_utilisateur');

        // Ajouter la logique de recherche
        if ('' !== $search) {
            $searchExpr = $qb->expr()->orX(
                $qb->expr()->like('p.type_poste_souhaite', ':search'),
                $qb->expr()->like('p.mode_travail', ':search'),
                $qb->expr()->like('p.disponibilite', ':search'),
                $qb->expr()->like('u.email', ':search')
            );

            if (ctype_digit($search)) {
                $searchExpr = $qb->expr()->orX(
                    $searchExpr,
                    $qb->expr()->eq('p.id_preference', ':search_int'),
                    $qb->expr()->eq('p.id_utilisateur', ':search_int')
                );
                $qb->setParameter(':search_int', (int) $search);
            }

            $qb
                ->andWhere($searchExpr)
                ->setParameter(':search', '%' . $search . '%');
        }

        $qb->orderBy($allowedSortFields[$sortBy], $sortOrder);
        $preferences = $qb->getQuery()->getResult();

        // Charger les User séparément
        $userIds = [];
        foreach ($preferences as $preference) {
            if ($preference->getIdUtilisateur()) {
                $userIds[$preference->getIdUtilisateur()] = true;
            }
        }
        
        $users = [];
        if (!empty($userIds)) {
            $userObjs = $entityManager->getRepository(User::class)->findBy(['id' => array_keys($userIds)]);
            foreach ($userObjs as $user) {
                $users[$user->getId()] = $user;
            }
        }

        return $this->render('admin/preferenceCandidature/index.html.twig', [
            'preferences' => $preferences,
            'users' => $users,
            'adminName' => (string) $session->get('admin_user_name', 'Admin'),
            'search' => $search,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
        ]);
    }

    #[Route('/preferences/{id}/edit', name: 'app_admin_preference_edit', methods: ['GET', 'POST'])]
    public function editPreference(PreferenceCandidature $preference, Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $fieldErrors = [];
        $formValues = [
            'type_poste_souhaite' => (string) ($preference->getTypePosteSouhaite() ?? ''),
            'mode_travail' => (string) ($preference->getModeTravail() ?? ''),
            'disponibilite' => (string) ($preference->getDisponibilite() ?? ''),
            'date_disponibilite' => $preference->getDateDisponibilite()?->format('Y-m-d') ?? '',
            'mobilite_geographique' => (string) ($preference->getMobiliteGeographique() ?? ''),
            'pret_deplacement' => (string) ($preference->getPretDeplacement() ?? ''),
            'type_contrat_souhaite' => (string) ($preference->getTypeContratSouhaite() ?? ''),
        ];

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('edit_preference_' . $preference->getIdPreference(), $token)) {
                $fieldErrors['_form'] = 'Token CSRF invalide.';
            }

            $typePosteSouhaite = trim((string) $request->request->get('type_poste_souhaite', ''));
            $modeTravail = trim((string) $request->request->get('mode_travail', ''));
            $disponibilite = trim((string) $request->request->get('disponibilite', ''));
            $mobiliteGeographique = trim((string) $request->request->get('mobilite_geographique', ''));
            $pretDeplacement = trim((string) $request->request->get('pret_deplacement', ''));
            $typeContratSouhaite = trim((string) $request->request->get('type_contrat_souhaite', ''));
            $dateDisp = trim((string) $request->request->get('date_disponibilite', ''));

            $formValues = [
                'type_poste_souhaite' => $typePosteSouhaite,
                'mode_travail' => $modeTravail,
                'disponibilite' => $disponibilite,
                'date_disponibilite' => $dateDisp,
                'mobilite_geographique' => $mobiliteGeographique,
                'pret_deplacement' => $pretDeplacement,
                'type_contrat_souhaite' => $typeContratSouhaite,
            ];

            if (mb_strlen($typePosteSouhaite) < 2 || mb_strlen($typePosteSouhaite) > 100) {
                $fieldErrors['type_poste_souhaite'] = 'Le type de poste doit contenir entre 2 et 100 caracteres.';
            }

            $allowedModeTravail = ['100% Présentiel', '100% Télétravail', 'Hybride'];
            if ($modeTravail === '' || !in_array($modeTravail, $allowedModeTravail, true)) {
                $fieldErrors['mode_travail'] = 'Mode de travail invalide.';
            }

            $allowedDisponibilite = ['Immédiatement', 'Dans 1 mois', 'Dans 3 mois', 'Dans 6 mois'];
            if ($disponibilite === '' || !in_array($disponibilite, $allowedDisponibilite, true)) {
                $fieldErrors['disponibilite'] = 'Disponibilite invalide.';
            }

            $allowedMobilite = ['Oui, national', 'Oui, région', 'Non'];
            if ($mobiliteGeographique === '' || !in_array($mobiliteGeographique, $allowedMobilite, true)) {
                $fieldErrors['mobilite_geographique'] = 'Mobilite geographique invalide.';
            }

            $allowedPretDeplacement = ['Jamais', 'Occasionnel', 'Fréquent'];
            if ($pretDeplacement === '' || !in_array($pretDeplacement, $allowedPretDeplacement, true)) {
                $fieldErrors['pret_deplacement'] = 'Pret au deplacement invalide.';
            }

            $allowedTypeContrat = ['CDI', 'CDD', 'Stage', 'Alternance', 'Freelance'];
            if ($typeContratSouhaite === '' || !in_array($typeContratSouhaite, $allowedTypeContrat, true)) {
                $fieldErrors['type_contrat_souhaite'] = 'Type de contrat invalide.';
            }

            $dateDisponibilite = null;
            if ($dateDisp !== '') {
                try {
                    $dateDisponibilite = new \DateTime($dateDisp);
                } catch (\Throwable) {
                    $fieldErrors['date_disponibilite'] = 'Date de disponibilite invalide.';
                }
            }

            if ($fieldErrors === []) {
                $preference->setTypePosteSouhaite($typePosteSouhaite);
                $preference->setModeTravail($modeTravail);
                $preference->setDisponibilite($disponibilite);
                $preference->setMobiliteGeographique($mobiliteGeographique);
                $preference->setPretDeplacement($pretDeplacement);
                $preference->setTypeContratSouhaite($typeContratSouhaite);
                $preference->setDateDisponibilite($dateDisponibilite);

                $entityManager->flush();
                $this->addFlash('success', 'Préférence modifiée avec succès.');

                return $this->redirectToRoute('app_admin_preferences');
            }
        }

        // Charger l'utilisateur
        $user = null;
        if ($preference->getIdUtilisateur()) {
            $user = $entityManager->getRepository(User::class)->find($preference->getIdUtilisateur());
        }

        return $this->render('admin/preferenceCandidature/edit.html.twig', [
            'preference' => $preference,
            'user' => $user,
            'adminName' => (string) $session->get('admin_user_name', 'Admin'),
            'fieldErrors' => $fieldErrors,
            'formValues' => $formValues,
        ]);
    }

    #[Route('/preferences/{id}/delete', name: 'app_admin_preference_delete', methods: ['POST'])]
    public function deletePreference(PreferenceCandidature $preference, Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        if (!$this->isCsrfTokenValid('delete_preference_' . $preference->getIdPreference(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_preferences');
        }

        try {
            $entityManager->remove($preference);
            $entityManager->flush();
            $this->addFlash('success', 'Préférence supprimée avec succès.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Erreur lors de la suppression.');
        }

        return $this->redirectToRoute('app_admin_preferences');
    }

    // ── Statistiques Candidatures et Préférences ──────────────────────
    #[Route('/candidatures-preferences/statistics', name: 'app_admin_statistics', methods: ['GET'])]
    public function statistics(EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        // ── Statistiques Candidatures ──
        $candidatures = $entityManager->getRepository(Candidature::class)->findAll();
        
        $totalCandidatures = count($candidatures);
        $candidaturesByStatus = [];
        $candidaturesByOffers = [];
        
        foreach ($candidatures as $candidature) {
            // Par statut
            $status = $candidature->getStatut() ?: 'Non spécifié';
            if (!isset($candidaturesByStatus[$status])) {
                $candidaturesByStatus[$status] = 0;
            }
            $candidaturesByStatus[$status]++;
            
            // Par offre
            $offre = $entityManager->getRepository(OffreEmploi::class)->find($candidature->getIdOffre());
            if ($offre) {
                $offreTitre = $offre->getTitre();
                if (!isset($candidaturesByOffers[$offreTitre])) {
                    $candidaturesByOffers[$offreTitre] = 0;
                }
                $candidaturesByOffers[$offreTitre]++;
            }
        }
        
        // ── Statistiques Préférences ──
        $preferences = $entityManager->getRepository(PreferenceCandidature::class)->findAll();
        
        $totalPreferences = count($preferences);
        $preferencesByType = [];
        $preferencesByMode = [];
        $preferencesByDisponibilite = [];
        
        foreach ($preferences as $preference) {
            // Par type de poste
            $type = $preference->getTypePosteSouhaite() ?: 'Non spécifié';
            if (!isset($preferencesByType[$type])) {
                $preferencesByType[$type] = 0;
            }
            $preferencesByType[$type]++;
            
            // Par mode de travail
            $mode = $preference->getModeTravail() ?: 'Non spécifié';
            if (!isset($preferencesByMode[$mode])) {
                $preferencesByMode[$mode] = 0;
            }
            $preferencesByMode[$mode]++;
            
            // Par disponibilité
            $dispo = $preference->getDisponibilite() ?: 'Non spécifié';
            if (!isset($preferencesByDisponibilite[$dispo])) {
                $preferencesByDisponibilite[$dispo] = 0;
            }
            $preferencesByDisponibilite[$dispo]++;
        }
        
        // Statistiques croisées
        $totalUsers = count($entityManager->getRepository(User::class)->findAll());
        $totalOffers = count($entityManager->getRepository(OffreEmploi::class)->findAll());
        
        // Taux de candidature (candidatures / utilisateurs)
        $rateOfCandidature = $totalUsers > 0 ? round(($totalCandidatures / $totalUsers) * 100, 2) : 0;
        
        // Taux de préférence (préférences / utilisateurs)
        $rateOfPreference = $totalUsers > 0 ? round(($totalPreferences / $totalUsers) * 100, 2) : 0;
        
        // Calculer les valeurs maximales pour les graphiques
        $maxStatus = count($candidaturesByStatus) > 0 ? max($candidaturesByStatus) : 0;
        $maxOffers = count($candidaturesByOffers) > 0 ? max($candidaturesByOffers) : 0;
        $maxType = count($preferencesByType) > 0 ? max($preferencesByType) : 0;
        $maxMode = count($preferencesByMode) > 0 ? max($preferencesByMode) : 0;
        $maxDispo = count($preferencesByDisponibilite) > 0 ? max($preferencesByDisponibilite) : 0;
        
        return $this->render('admin/candidature/statistics.html.twig', [
            'adminName' => (string) $session->get('admin_user_name', 'Admin'),
            // Candidatures
            'totalCandidatures' => $totalCandidatures,
            'candidaturesByStatus' => $candidaturesByStatus,
            'candidaturesByOffers' => $candidaturesByOffers,
            'maxStatus' => $maxStatus,
            'maxOffers' => $maxOffers,
            // Préférences
            'totalPreferences' => $totalPreferences,
            'preferencesByType' => $preferencesByType,
            'preferencesByMode' => $preferencesByMode,
            'preferencesByDisponibilite' => $preferencesByDisponibilite,
            'maxType' => $maxType,
            'maxMode' => $maxMode,
            'maxDispo' => $maxDispo,
            // Données croisées
            'totalUsers' => $totalUsers,
            'totalOffers' => $totalOffers,
            'rateOfCandidature' => $rateOfCandidature,
            'rateOfPreference' => $rateOfPreference,
        ]);
    }

    private function requireAdmin(SessionInterface $session): RedirectResponse|null
    {
        $adminId = $session->get('admin_user_id');
        $adminRole = (string) $session->get('admin_user_role', '');

        if (!$adminId || !str_starts_with($adminRole, 'ADMIN')) {
            $this->addFlash('error', 'Veuillez vous connecter en admin.');
            return $this->redirectToRoute('app_signin');
        }

        return null;
    }
}

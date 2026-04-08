<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\OffreEmploi;
use App\Entity\PreferenceCandidature;
use App\Entity\User;
use App\Form\AdminUserType;
use App\Service\AdminDashboardService;
use App\Service\AdminUserService;
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

        $search = (string) $request->query->get('search', '');
        $sortBy = (string) $request->query->get('sortBy', 'id_candidature');
        $sortOrder = (string) $request->query->get('sortOrder', 'DESC');

        $qb = $entityManager->getRepository(Candidature::class)->createQueryBuilder('c')
            ->orderBy('c.' . $sortBy, $sortOrder);
        
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

        if ($request->isMethod('POST')) {
            $candidature->setStatut((string) $request->request->get('statut', $candidature->getStatut()));
            $entityManager->flush();
            $this->addFlash('success', 'Candidature modifiée avec succès.');
            return $this->redirectToRoute('app_admin_candidatures');
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

        $qb = $entityManager->getRepository(PreferenceCandidature::class)->createQueryBuilder('p');
        
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

        if ($request->isMethod('POST')) {
            $preference->setTypePosteSouhaite((string) $request->request->get('type_poste_souhaite'));
            $preference->setModeTravail((string) $request->request->get('mode_travail'));
            $preference->setDisponibilite((string) $request->request->get('disponibilite'));
            $preference->setMobiliteGeographique((string) $request->request->get('mobilite_geographique'));
            $preference->setPretDeplacement((string) $request->request->get('pret_deplacement'));
            $preference->setTypeContratSouhaite((string) $request->request->get('type_contrat_souhaite'));
            
            // Traiter la date de disponibilité
            $dateDisp = $request->request->get('date_disponibilite');
            if ($dateDisp) {
                $preference->setDateDisponibilite(new \DateTime($dateDisp));
            }
            
            $entityManager->flush();
            $this->addFlash('success', 'Préférence modifiée avec succès.');
            return $this->redirectToRoute('app_admin_preferences');
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

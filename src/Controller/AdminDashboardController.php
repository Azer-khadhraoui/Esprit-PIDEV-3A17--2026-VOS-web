<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminDashboardController extends AbstractController
{
    public function dashboard(Request $request): Response
    {
        return $this->renderDashboard('Dashboard Utilisateurs', $request);
    }

    public function offreDashboard(Request $request): Response
    {
        return $this->renderDashboard('Dashboard Offres', $request);
    }

    public function candidatures(Request $request): Response
    {
        return $this->renderDashboard('Dashboard Candidatures', $request);
    }

    public function entretiens(Request $request): Response
    {
        return $this->render('admin/entretiens.html.twig', [
            'adminName' => 'Khadhraoui Azer',
            'search' => $request->query->get('search', ''),
            'typeFilter' => $request->query->get('type', ''),
            'statutFilter' => $request->query->get('statut', ''),
            'entretiens' => $this->getSampleEntretiens(),
        ]);
    }

    public function recrutements(Request $request): Response
    {
        return $this->renderDashboard('Dashboard Recrutements', $request);
    }

    public function forum(Request $request): Response
    {
        return $this->renderDashboard('Forum', $request);
    }

    public function reports(Request $request): Response
    {
        return $this->renderDashboard('Rapports', $request);
    }

    public function statistiques(Request $request): Response
    {
        return $this->renderDashboard('Statistiques utilisateurs', $request);
    }

    public function logout(): RedirectResponse
    {
        return $this->redirectToRoute('app_admin_dashboard');
    }

    private function renderDashboard(string $title, Request $request): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'pageTitle' => $title,
            'adminName' => 'Khadhraoui Azer',
            'stats' => [
                'total' => 10,
                'admins' => 2,
                'clients' => 8,
            ],
            'users' => $this->getSampleUsers(),
            'search' => $request->query->get('search', ''),
            'roleFilter' => $request->query->get('role', ''),
            'sortBy' => $request->query->get('sortBy', 'id'),
            'sortOrder' => $request->query->get('sortOrder', 'DESC'),
        ]);
    }

    private function getSampleUsers(): array
    {
        return [
            ['id' => 101, 'imageProfil' => null, 'nom' => 'Ben Salah', 'prenom' => 'Maha', 'email' => 'maha.bensalah@example.com', 'role' => 'ADMIN_RH'],
            ['id' => 102, 'imageProfil' => null, 'nom' => 'Trabelsi', 'prenom' => 'Nizar', 'email' => 'nizar.trabelsi@example.com', 'role' => 'CLIENT'],
            ['id' => 103, 'imageProfil' => null, 'nom' => 'Saidi', 'prenom' => 'Amina', 'email' => 'amina.saidi@example.com', 'role' => 'ADMIN_TECHNIQUE'],
        ];
    }

    private function getSampleEntretiens(): array
    {
        return [
            ['id' => 13, 'date' => '05/04/2026', 'heure' => '15:35', 'type' => 'Technique', 'testType' => 'diagnostic', 'statut' => 'Terminé', 'lieu' => 'en ligne'],
            ['id' => 10, 'date' => '14/03/2026', 'heure' => '10:00', 'type' => 'RH', 'testType' => 'rh', 'statut' => 'Terminé', 'lieu' => 'en ligne'],
            ['id' => 11, 'date' => '05/03/2026', 'heure' => '10:00', 'type' => 'RH', 'testType' => 'technique', 'statut' => 'Terminé', 'lieu' => 'en ligne'],
        ];
    }
}

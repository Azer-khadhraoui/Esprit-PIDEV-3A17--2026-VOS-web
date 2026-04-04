<?php

namespace App\Controller;

use App\Service\ClientOffreService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/client')]
class ClientController extends AbstractController
{
    #[Route('/offres', name: 'app_client_offres')]
    public function offres(Request $request, ClientOffreService $offreService, SessionInterface $session): Response
    {
        $userId = $session->get('admin_user_id') ?: $session->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_signin');
        }

        $search = (string) $request->query->get('search', '');
        $type = (string) $request->query->get('type', '');

        if (!empty($type)) {
            $offres = $offreService->filterByType($type);
        } elseif (!empty($search)) {
            $offres = $offreService->searchOffres($search);
        } else {
            $offres = $offreService->getAllOffres();
        }

        return $this->render('client/offres.html.twig', [
            'offres' => $offres,
            'userName' => (string) $session->get('user_name', 'Client'),
            'search' => $search,
            'type' => $type,
        ]);
    }
}

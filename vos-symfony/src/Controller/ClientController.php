<?php

namespace App\Controller;

use App\Dto\ClientProfileDto;
use App\Form\ClientProfileType;
use App\Repository\UserRepository;
use App\Service\ClientOffreService;
use App\Service\ClientProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/client')]
class ClientController extends AbstractController
{
    #[Route('/accueil', name: 'app_client_accueil', methods: ['GET'])]
    public function accueil(SessionInterface $session): Response
    {
        $access = $this->requireClient($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        return $this->render('client/accueil.html.twig', [
            'userName' => (string) $session->get('user_name', 'Client'),
        ]);
    }

    #[Route('/offres', name: 'app_client_offres')]
    public function offres(Request $request, ClientOffreService $offreService, SessionInterface $session): Response
    {
        $access = $this->requireClient($session);
        if ($access instanceof RedirectResponse) {
            return $access;
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
            'userRole' => (string) $session->get('user_role', 'CLIENT'),
            'search' => $search,
            'type' => $type,
        ]);
    }

    #[Route('/profile', name: 'app_client_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        SessionInterface $session,
        UserRepository $userRepository,
        ClientProfileService $profileService
    ): Response {
        $access = $this->requireClient($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $userId = (int) $session->get('user_id', 0);
        $user = $userRepository->find($userId);

        if (!$user) {
            $this->addFlash('error', 'Compte introuvable. Veuillez vous reconnecter.');
            return $this->redirectToRoute('app_signin');
        }

        $profileDto = new ClientProfileDto();
        $profileDto->nom = $user->getNom();
        $profileDto->prenom = $user->getPrenom();
        $profileDto->email = $user->getEmail();

        $form = $this->createForm(ClientProfileType::class, $profileDto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $uploadedImage = $form->get('imageFile')->getData();
                $profileService->updateProfile($user, $profileDto, $uploadedImage);

                $session->set('user_name', trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')));
                $session->save();

                $this->addFlash('success', 'Profil mis à jour avec succès.');
                return $this->redirectToRoute('app_client_profile');
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('client/profile.html.twig', [
            'profileForm' => $form->createView(),
            'user' => $user,
            'userName' => (string) $session->get('user_name', 'Client'),
        ]);
    }

    private function requireClient(SessionInterface $session): RedirectResponse|null
    {
        $clientId = $session->get('user_id');
        $clientRole = (string) $session->get('user_role', '');

        if (!$clientId || $clientRole !== 'CLIENT') {
            $this->addFlash('error', 'Veuillez vous connecter en client.');
            return $this->redirectToRoute('app_signin');
        }

        return null;
    }
}

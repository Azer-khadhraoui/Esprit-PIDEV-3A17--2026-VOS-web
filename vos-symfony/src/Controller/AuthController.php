<?php

namespace App\Controller;

use App\Dto\SignupDto;
use App\Form\SignupType;
use App\Service\UserAccountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('app_client_accueil');
    }

    #[Route('/signup', name: 'app_signup')]
    public function signup(
        Request $request,
        UserAccountService $userAccountService,
        SessionInterface $session
    ): Response {
        $redirect = $this->redirectAuthenticatedUser($session);
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        $signupDto = new SignupDto();
        $form = $this->createForm(SignupType::class, $signupDto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $userAccountService->register($signupDto, $form->get('imageFile')->getData());

                $this->addFlash('success', sprintf(
                    'Bienvenue %s %s, votre compte a ete cree avec succes !',
                    $signupDto->prenom,
                    $signupDto->nom
                ));

                return $this->redirectToRoute('app_signup');
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
                return $this->redirectToRoute('app_signup');
            }
        }

        return $this->render('auth/signup.html.twig', [
            'signupForm' => $form->createView(),
        ]);
    }

    #[Route('/signin', name: 'app_signin')]
    public function signin(
        Request $request,
        UserAccountService $userAccountService,
        SessionInterface $session
    ): Response
    {
        $redirect = $this->redirectAuthenticatedUser($session);
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        if ($request->isMethod('POST')) {
            try {
                $email = (string) $request->request->get('email', '');
                $password = (string) $request->request->get('password', '');

                $user = $userAccountService->authenticateAdmin($email, $password);

                if (!$user) {
                    // Essayer une connexion client
                    $user = $userAccountService->authenticateUser($email, $password);

                    if (!$user) {
                        $this->addFlash('error', 'Email ou mot de passe invalide.');
                        return $this->redirectToRoute('app_signin');
                    }

                    // Session client
                    if (!$session->isStarted()) {
                        $session->start();
                    }

                    // Ensure only one active auth context at a time.
                    $session->remove('admin_user_id');
                    $session->remove('admin_user_role');
                    $session->remove('admin_user_name');

                    $session->set('user_id', $user->getId());
                    $session->set('user_role', $user->getRole());
                    $session->set('user_name', trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')));
                    $session->set('auth_scope', 'client');
                    $session->save();

                    return $this->redirectToRoute('client_opportunites');
                }

                // Session admin
                if (!$session->isStarted()) {
                    $session->start();
                }

                // Ensure only one active auth context at a time.
                $session->remove('user_id');
                $session->remove('user_role');
                $session->remove('user_name');

                $session->set('admin_user_id', $user->getId());
                $session->set('admin_user_role', $user->getRole());
                $session->set('admin_user_name', trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')));
                $session->set('auth_scope', 'admin');

                // Sauvegarder explicitement la session
                $session->save();

                return $this->redirectToRoute('app_admin_dashboard');
            } catch (\Throwable) {
                $this->addFlash('error', 'Email ou mot de passe invalide.');
                return $this->redirectToRoute('app_signin');
            }
        }

        return $this->render('auth/signin.html.twig', []);
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(SessionInterface $session): Response
    {
        $session->remove('admin_user_id');
        $session->remove('admin_user_role');
        $session->remove('admin_user_name');
        $session->remove('user_id');
        $session->remove('user_role');
        $session->remove('user_name');
        $session->remove('auth_scope');

        $this->addFlash('success', 'Déconnexion réussie.');

        return $this->redirectToRoute('app_client_accueil');
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('static/contact.html.twig');
    }

    #[Route('/about', name: 'app_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('static/about.html.twig');
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(): Response
    {
        return $this->render('auth/forgot_password.html.twig');
    }

    #[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(): Response
    {
        return $this->render('auth/reset_password.html.twig');
    }

    private function redirectAuthenticatedUser(SessionInterface $session): ?RedirectResponse
    {
        $adminUserId = (int) $session->get('admin_user_id', 0);
        $adminRole = (string) $session->get('admin_user_role', '');
        if ($adminUserId > 0 && str_starts_with($adminRole, 'ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $userId = (int) $session->get('user_id', 0);
        $userRole = (string) $session->get('user_role', '');
        if ($userId > 0 && $userRole === 'CLIENT') {
            return $this->redirectToRoute('app_client_accueil');
        }

        return null;
    }
}

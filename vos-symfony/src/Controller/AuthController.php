<?php

namespace App\Controller;

use App\Dto\SignupDto;
use App\Entity\User;
use App\Form\SignupType;
use App\Repository\UserRepository;
use App\Service\PasswordResetService;
use App\Service\UserAccountService;
use App\Service\ValidationService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        UserRepository $userRepository,
        SessionInterface $session
    ): Response
    {
        if (!$session->has('user_id') && !$session->has('admin_user_id')) {
            $rememberedUser = $this->getRememberedUser($request, $userRepository);
            if ($rememberedUser instanceof User) {
                $this->createSessionForUser($session, $rememberedUser);

                $response = $this->redirectToRoute(str_starts_with($rememberedUser->getRole(), 'ADMIN') ? 'app_admin_dashboard' : 'client_opportunites');
                return $this->attachRememberMeCookieIfNeeded($request, $response, $rememberedUser);
            }
        }

        $redirect = $this->redirectAuthenticatedUser($session);
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        if ($request->isMethod('POST')) {
            try {
                $token = (string) $request->request->get('_token');
                if (!$this->isCsrfTokenValid('signin_form', $token)) {
                    $this->addFlash('error', 'Token invalide. Veuillez reessayer.');
                    return $this->redirectToRoute('app_signin');
                }

                $email = trim((string) $request->request->get('email', ''));
                $password = (string) $request->request->get('password', '');
                $faceAuthMode = (string) $request->request->get('face_auth_mode', '0');
                $faceSimilarity = (float) $request->request->get('face_similarity', 0);

                if ($email === '') {
                    $this->addFlash('error', 'Email obligatoire.');
                    return $this->redirectToRoute('app_signin');
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->addFlash('error', 'Format email invalide.');
                    return $this->redirectToRoute('app_signin');
                }

                if ($faceAuthMode === '1') {
                    if ($faceSimilarity < 64.0) {
                        $this->addFlash('error', sprintf('NN: similitude insuffisante (%.1f%%).', $faceSimilarity));
                        return $this->redirectToRoute('app_signin');
                    }

                    $user = $userRepository->findByEmail($email);
                    if (!$user || !$user->getImageProfil()) {
                        $this->addFlash('error', 'Compte introuvable ou image de reference absente.');
                        return $this->redirectToRoute('app_signin');
                    }

                    $this->createSessionForUser($session, $user);

                    if (str_starts_with($user->getRole(), 'ADMIN')) {
                        return $this->redirectToRoute('app_admin_dashboard');
                    }

                    return $this->redirectToRoute('client_opportunites');
                }

                if ($password === '') {
                    $this->addFlash('error', 'Mot de passe obligatoire ou utilisez la reconnaissance faciale.');
                    return $this->redirectToRoute('app_signin');
                }

                $user = $userAccountService->authenticateAdmin($email, $password);

                if (!$user) {
                    // Essayer une connexion client
                    $user = $userAccountService->authenticateUser($email, $password);

                    if (!$user) {
                        $this->addFlash('error', 'Email ou mot de passe invalide.');
                        return $this->redirectToRoute('app_signin');
                    }

                    $this->createSessionForUser($session, $user);

                    $response = $this->redirectToRoute('client_opportunites');
                    return $this->attachRememberMeCookieIfNeeded($request, $response, $user);
                }

                $this->createSessionForUser($session, $user);

                $response = $this->redirectToRoute('app_admin_dashboard');
                return $this->attachRememberMeCookieIfNeeded($request, $response, $user);
            } catch (\Throwable) {
                $this->addFlash('error', 'Email ou mot de passe invalide.');
                return $this->redirectToRoute('app_signin');
            }
        }

        return $this->render('auth/signin.html.twig', []);
    }

    #[Route('/signin/face-reference', name: 'app_signin_face_reference', methods: ['GET'])]
    public function signinFaceReference(Request $request, UserRepository $userRepository): JsonResponse
    {
        $email = trim((string) $request->query->get('email', ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['ok' => false, 'message' => 'Email invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findByEmail($email);
        if (!$user) {
            return new JsonResponse(['ok' => false, 'message' => 'Compte introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $faceDescriptor = null;
        if ($user->getFaceDescriptor()) {
            $decoded = json_decode((string) $user->getFaceDescriptor(), true);
            if (is_array($decoded)) {
                $faceDescriptor = array_map(static fn ($value) => (float) $value, $decoded);
            }
        }

        if (!$user->getImageProfil() && !$faceDescriptor) {
            return new JsonResponse(['ok' => false, 'message' => 'Aucune reference faciale disponible.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'ok' => true,
            'imageUrl' => $user->getImageProfil(),
            'referenceDescriptor' => $faceDescriptor,
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(SessionInterface $session): Response
    {
        if (!$session->isStarted()) {
            $session->start();
        }

        $session->remove('admin_user_id');
        $session->remove('admin_user_role');
        $session->remove('admin_user_name');
        $session->remove('user_id');
        $session->remove('user_role');
        $session->remove('user_name');
        $session->remove('auth_scope');
        $session->invalidate();

        $this->addFlash('success', 'Déconnexion réussie.');

        $response = $this->redirectToRoute('app_signin');
        $response->headers->clearCookie(session_name());
        $response->headers->clearCookie('vos_remember_me');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('static/contact.html.twig');
    }

    #[Route('/about', name: 'app_about', methods: ['GET'])]
    public function about(Request $request): Response
    {
        $configuredPublicUrl = trim((string) ($_ENV['APP_PUBLIC_URL'] ?? $_SERVER['APP_PUBLIC_URL'] ?? ''));
        $appUrl = $this->resolvePublicBaseUrl($request, $configuredPublicUrl);

        return $this->render('static/about.html.twig', [
            'appUrl' => $appUrl,
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, PasswordResetService $passwordResetService, ValidationService $validation): Response
    {
        $emailValue = '';

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('forgot_password_request', $token)) {
                $this->addFlash('error', 'Session invalide, veuillez reessayer.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $emailValue = trim((string) $request->request->get('email', ''));

            try {
                $email = $validation->validateEmail($emailValue);
                $passwordResetService->requestReset($email, $request->getClientIp());

                // Message volontairement generique pour eviter l'enumeration d'emails.
                $this->addFlash('success', 'Si un compte existe avec cet email, un code de reinitialisation a ete envoye.');
                return $this->redirectToRoute('app_reset_password', ['email' => $email]);
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('auth/forgot_password.html.twig', [
            'emailValue' => $emailValue,
        ]);
    }

    #[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, PasswordResetService $passwordResetService, ValidationService $validation): Response
    {
        $emailValue = trim((string) $request->query->get('email', ''));

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('reset_password_form', $csrfToken)) {
                $this->addFlash('error', 'Session invalide, veuillez reessayer.');
                return $this->redirectToRoute('app_reset_password');
            }

            $emailValue = trim((string) $request->request->get('email', ''));
            $code = preg_replace('/\D+/', '', (string) $request->request->get('code', ''));

            $password = (string) $request->request->get('password', '');
            $confirmPassword = (string) $request->request->get('confirmPassword', '');

            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_reset_password', ['email' => $emailValue]);
            }

            try {
                $email = $validation->validateEmail($emailValue);
                if (strlen((string) $code) !== 6) {
                    throw new \RuntimeException('Le code de reinitialisation doit contenir 6 chiffres.');
                }

                $validPassword = $validation->validatePassword($password, 8, 'mot de passe');
                $changed = $passwordResetService->resetPasswordWithCode($email, (string) $code, $validPassword);

                if (!$changed) {
                    $this->addFlash('error', 'Code invalide ou expire. Veuillez refaire une demande.');
                    return $this->redirectToRoute('app_forgot_password');
                }

                $this->addFlash('success', 'Mot de passe reinitialise avec succes. Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('app_signin');
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
                return $this->redirectToRoute('app_reset_password', ['email' => $emailValue]);
            }
        }

        return $this->render('auth/reset_password.html.twig', [
            'emailValue' => $emailValue,
        ]);
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
            return $this->redirectToRoute('client_opportunites');
        }

        return null;
    }

    private function createSessionForUser(SessionInterface $session, User $user): void
    {
        if (!$session->isStarted()) {
            $session->start();
        }

        if (str_starts_with($user->getRole(), 'ADMIN')) {
            $session->remove('user_id');
            $session->remove('user_role');
            $session->remove('user_name');

            $session->set('admin_user_id', $user->getId());
            $session->set('admin_user_role', $user->getRole());
            $session->set('admin_user_name', trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')));
            $session->set('auth_scope', 'admin');
            $session->save();

            return;
        }

        $session->remove('admin_user_id');
        $session->remove('admin_user_role');
        $session->remove('admin_user_name');

        $session->set('user_id', $user->getId());
        $session->set('user_role', $user->getRole());
        $session->set('user_name', trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')));
        $session->set('auth_scope', 'client');
        $session->save();
    }

    private function attachRememberMeCookieIfNeeded(Request $request, Response $response, User $user): Response
    {
        if (!$request->isMethod('POST')) {
            return $response;
        }

        if (!$this->isRememberMeRequested($request)) {
            $response->headers->clearCookie('vos_remember_me');
            return $response;
        }

        $response->headers->setCookie($this->createRememberMeCookie($user, $request->isSecure()));

        return $response;
    }

    private function isRememberMeRequested(Request $request): bool
    {
        $value = $request->request->all()['remember'] ?? $request->request->all()['_remember_me'] ?? null;

        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'on', 'true', 'yes'], true);
    }

    private function getRememberedUser(Request $request, UserRepository $userRepository): ?User
    {
        $cookieValue = (string) $request->cookies->get('vos_remember_me', '');
        if ($cookieValue === '') {
            return null;
        }

        $decoded = base64_decode($cookieValue, true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 6) {
            return null;
        }

        [$userId, $email, $role, $passwordHash, $expiresAt, $signature] = $parts;
        if (!ctype_digit($userId) || !ctype_digit($expiresAt)) {
            return null;
        }

        if ((int) $expiresAt < time()) {
            return null;
        }

        $user = $userRepository->find((int) $userId);
        if (!$user) {
            return null;
        }

        $expectedPayload = implode('|', [$userId, $email, $role, $passwordHash, $expiresAt]);
        $expectedSignature = hash_hmac('sha256', $expectedPayload, (string) $this->getParameter('kernel.secret'));

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        if ($user->getEmail() !== $email || $user->getRole() !== $role || $user->getPassword() !== $passwordHash) {
            return null;
        }

        return $user;
    }

    private function createRememberMeCookie(User $user, bool $secure): Cookie
    {
        $expiresAt = new \DateTimeImmutable('+30 days');
        $payload = implode('|', [
            (string) $user->getId(),
            (string) $user->getEmail(),
            (string) $user->getRole(),
            (string) $user->getPassword(),
            (string) $expiresAt->getTimestamp(),
        ]);
        $signature = hash_hmac('sha256', $payload, (string) $this->getParameter('kernel.secret'));

        return Cookie::create('vos_remember_me', base64_encode($payload . '|' . $signature), $expiresAt, '/', null, $secure, true, false, Cookie::SAMESITE_LAX);
    }

    private function resolvePublicBaseUrl(Request $request, string $configuredPublicUrl): string
    {
        if ($configuredPublicUrl !== '') {
            return rtrim($configuredPublicUrl, '/');
        }

        $requestBaseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
        if (!$this->isLocalHost($request->getHost())) {
            return $requestBaseUrl;
        }

        $detectedLanIp = $this->detectLanIp();
        if ($detectedLanIp === null) {
            return $requestBaseUrl;
        }

        $scheme = $request->isSecure() ? 'https' : 'http';
        $port = $request->getPort();
        $defaultPort = $request->isSecure() ? 443 : 80;
        $portSuffix = $port !== $defaultPort ? ':' . $port : '';

        return sprintf('%s://%s%s', $scheme, $detectedLanIp, $portSuffix);
    }

    private function isLocalHost(string $host): bool
    {
        $normalizedHost = strtolower(trim($host));

        return in_array($normalizedHost, ['localhost', '127.0.0.1', '::1'], true);
    }

    private function detectLanIp(): ?string
    {
        $hostnameIp = gethostbyname(gethostname());
        if (!$this->isPrivateIpv4($hostnameIp)) {
            return null;
        }

        return $hostnameIp;
    }

    private function isPrivateIpv4(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if (str_starts_with($ip, '127.')) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
    }
}

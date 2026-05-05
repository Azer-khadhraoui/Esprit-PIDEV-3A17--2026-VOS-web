<?php

namespace App\Controller;

use App\Dto\Admin\CountStatDto;
use App\Entity\Candidature;
use App\Entity\OffreEmploi;
use App\Entity\PreferenceCandidature;
use App\Entity\User;
use App\Form\AdminUserType;
use App\Service\AdminDashboardService;
use App\Service\candidature\MatchingService;
use App\Service\AdminUserService;
use App\Service\candidature\PdfService;
use App\Service\GroqReasonEnhancer;
use App\Service\LanguageToolReasonEnhancer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Nucleos\DompdfBundle\Wrapper\DompdfWrapperInterface;

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
        $limit = max(10, min(500, (int) $request->query->get('limit', 100)));

        $users = $dashboardService->getUsers($search, $sortBy, $sortOrder, $roleFilter, $limit);
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
            'limit' => $limit,
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

    #[Route('/users/service-administratif', name: 'app_admin_users_administrative_service', methods: ['GET', 'POST'])]
    public function administrativeService(Request $request, SessionInterface $session, GroqReasonEnhancer $reasonEnhancer, LanguageToolReasonEnhancer $languageToolEnhancer, DompdfWrapperInterface $pdfWrapper, UrlGeneratorInterface $urlGenerator): Response
    {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $formData = [
            'request_type' => 'conge',
            'full_name' => (string) $session->get('admin_user_name', 'Admin'),
            'email' => '',
            'position' => '',
            'department' => '',
            'start_date' => $today,
            'end_date' => $today,
            'reason' => '',
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('admin_administrative_service', $token)) {
                $errors['_form'] = 'Token CSRF invalide. Merci de reessayer.';
            }

            $formData['request_type'] = trim((string) $request->request->get('request_type', ''));
            $formData['full_name'] = trim((string) $request->request->get('full_name', ''));
            $formData['email'] = trim((string) $request->request->get('email', ''));
            $formData['position'] = trim((string) $request->request->get('position', ''));
            $formData['department'] = trim((string) $request->request->get('department', ''));
            $formData['start_date'] = trim((string) $request->request->get('start_date', ''));
            $formData['end_date'] = trim((string) $request->request->get('end_date', ''));
            $formData['reason'] = trim((string) $request->request->get('reason', ''));

            if (!in_array($formData['request_type'], ['demission', 'conge'], true)) {
                $errors['request_type'] = 'Type de demande invalide.';
            }

            if (mb_strlen($formData['full_name']) < 3 || mb_strlen($formData['full_name']) > 120) {
                $errors['full_name'] = 'Le nom complet doit contenir entre 3 et 120 caracteres.';
            }

            if ($formData['email'] === '' || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Adresse email invalide.';
            }

            if (mb_strlen($formData['position']) < 2 || mb_strlen($formData['position']) > 100) {
                $errors['position'] = 'Le poste doit contenir entre 2 et 100 caracteres.';
            }

            if ($formData['department'] !== '' && mb_strlen($formData['department']) > 100) {
                $errors['department'] = 'Le departement ne doit pas depasser 100 caracteres.';
            }

            $action = (string) $request->request->get('_action', 'generate_pdf');
            $isEnhanceAction = $action === 'enhance_reason';
            $isLanguageToolAction = $action === 'enhance_reason_languagetool';

            if ($isEnhanceAction || $isLanguageToolAction) {
                if (mb_strlen($formData['reason']) < 5 || mb_strlen($formData['reason']) > 2000) {
                    $errors['reason'] = 'Le motif doit contenir entre 5 et 2000 caracteres pour etre ameliore.';
                }
            } else {
                if (mb_strlen($formData['reason']) < 10 || mb_strlen($formData['reason']) > 2000) {
                    $errors['reason'] = 'Le motif doit contenir entre 10 et 2000 caracteres.';
                }
            }

            $startDate = null;
            if ($formData['start_date'] === '') {
                $errors['start_date'] = 'La date de debut est obligatoire.';
            } else {
                try {
                    $startDate = new \DateTimeImmutable($formData['start_date']);
                } catch (\Throwable) {
                    $errors['start_date'] = 'Date de debut invalide.';
                }
            }

            $endDate = null;
            if ($formData['request_type'] === 'conge') {
                if ($formData['end_date'] === '') {
                    $errors['end_date'] = 'La date de fin est obligatoire pour une demande de conge.';
                } else {
                    try {
                        $endDate = new \DateTimeImmutable($formData['end_date']);
                    } catch (\Throwable) {
                        $errors['end_date'] = 'Date de fin invalide.';
                    }
                }

                if ($startDate instanceof \DateTimeImmutable && $endDate instanceof \DateTimeImmutable && $endDate < $startDate) {
                    $errors['end_date'] = 'La date de fin doit etre superieure ou egale a la date de debut.';
                }
            } else {
                $formData['end_date'] = '';
            }

            if ($startDate instanceof \DateTimeImmutable) {
                $todayDate = new \DateTimeImmutable('today');
                if ($startDate < $todayDate) {
                    $errors['start_date'] = 'La date de debut ne peut pas etre dans le passe.';
                }
            }

            if ($formData['request_type'] === 'conge' && $endDate instanceof \DateTimeImmutable) {
                $todayDate = new \DateTimeImmutable('today');
                if ($endDate < $todayDate) {
                    $errors['end_date'] = 'La date de fin ne peut pas etre dans le passe.';
                }
            }

            if ($isEnhanceAction && $errors === []) {
                try {
                    $formData['reason'] = $reasonEnhancer->enhance($formData['reason'], $formData['request_type']);
                    $this->addFlash('success', 'Motif ameliore avec IA. Verifiez puis cliquez sur Generer PDF.');
                } catch (\Throwable $exception) {
                    $errors['_form'] = $exception->getMessage();
                }
            }

            if ($isLanguageToolAction && $errors === []) {
                try {
                    $originalReason = $formData['reason'];
                    $formData['reason'] = $languageToolEnhancer->enhance($formData['reason']);

                    if ($formData['reason'] === $originalReason) {
                        $this->addFlash('success', 'Aucune correction detectee par LanguageTool.');
                    } else {
                        $this->addFlash('success', 'Motif corrige avec LanguageTool. Verifiez puis cliquez sur Generer PDF.');
                    }
                } catch (\Throwable $exception) {
                    $errors['_form'] = $exception->getMessage();
                }
            }

            if (!$isEnhanceAction && !$isLanguageToolAction && $errors === []) {
                $reference = sprintf('ADM-%s-%s', (new \DateTimeImmutable())->format('Ymd'), strtoupper(substr(sha1($formData['email'] . microtime(true)), 0, 6)));
                $issuedAt = (new \DateTimeImmutable())->getTimestamp();
                $verificationPayload = [
                    'ref' => $reference,
                    'type' => $formData['request_type'],
                    'name' => $formData['full_name'],
                    'issued' => (string) $issuedAt,
                ];
                $verificationSignature = $this->createDocumentSignature(
                    $verificationPayload['ref'],
                    $verificationPayload['type'],
                    $verificationPayload['name'],
                    $verificationPayload['issued']
                );

                $verificationPath = $urlGenerator->generate('app_admin_document_verify', [
                    'ref' => $verificationPayload['ref'],
                    'type' => $verificationPayload['type'],
                    'name' => $verificationPayload['name'],
                    'issued' => $verificationPayload['issued'],
                    'sig' => $verificationSignature,
                ], UrlGeneratorInterface::ABSOLUTE_PATH);

                $configuredPublicUrl = trim((string) ($_ENV['APP_PUBLIC_URL'] ?? $_SERVER['APP_PUBLIC_URL'] ?? ''));
                $publicBaseUrl = $this->resolvePublicBaseUrl($request, $configuredPublicUrl);

                $verificationUrl = $publicBaseUrl . $verificationPath;

                $downloadPath = $urlGenerator->generate('app_admin_document_download', [
                    'ref' => $verificationPayload['ref'],
                    'type' => $verificationPayload['type'],
                    'name' => $verificationPayload['name'],
                    'issued' => $verificationPayload['issued'],
                    'sig' => $verificationSignature,
                ], UrlGeneratorInterface::ABSOLUTE_PATH);
                $downloadUrl = $publicBaseUrl . $downloadPath;

                $pdfHtml = $this->renderView('admin/administrative_service_pdf.html.twig', [
                    'formData' => $formData,
                    'submittedAt' => new \DateTimeImmutable(),
                    'reference' => $reference,
                    'qrPayload' => $verificationUrl,
                    'verificationUrl' => $verificationUrl,
                    'downloadUrl' => $downloadUrl,
                ]);

                $typePrefix = $formData['request_type'] === 'demission' ? 'demission' : 'conge';
                $filename = sprintf('%s_%s.pdf', $typePrefix, (new \DateTimeImmutable())->format('Ymd_His'));

                $pdfBinary = $pdfWrapper->getPdf($pdfHtml);

                $storageDir = $this->getParameter('kernel.project_dir') . '/var/generated_pdfs';
                if (!is_dir($storageDir)) {
                    @mkdir($storageDir, 0777, true);
                }
                @file_put_contents($this->getGeneratedPdfPath($reference), $pdfBinary);

                $response = new Response($pdfBinary);
                $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
                $response->headers->set('Content-Type', 'application/pdf');
                $response->headers->set('Content-Disposition', $disposition);

                return $response;
            }
        }

        return $this->render('admin/administrative_service.html.twig', [
            'adminName' => (string) $session->get('admin_user_name', 'Admin'),
            'formData' => $formData,
            'errors' => $errors,
            'today' => $today,
            'isGroqConfigured' => $reasonEnhancer->isConfigured(),
        ]);
    }

    #[Route('/document/verify', name: 'app_admin_document_verify', methods: ['GET'])]
    public function verifyDocument(Request $request): Response
    {
        $reference = trim((string) $request->query->get('ref', ''));
        $type = trim((string) $request->query->get('type', ''));
        $name = trim((string) $request->query->get('name', ''));
        $issued = trim((string) $request->query->get('issued', ''));
        $signature = trim((string) $request->query->get('sig', ''));

        $isValid = false;
        $message = 'Code invalide.';

        if ($reference !== '' && $type !== '' && $name !== '' && ctype_digit($issued) && $signature !== '') {
            $expectedSignature = $this->createDocumentSignature($reference, $type, $name, $issued);
            $isValid = hash_equals($expectedSignature, $signature);

            if ($isValid) {
                $message = 'Document authentique genere par VOS Backoffice.';
            } else {
                $message = 'Signature invalide: document potentiellement modifie.';
            }
        }

        $issuedAt = ctype_digit($issued) ? (new \DateTimeImmutable())->setTimestamp((int) $issued) : null;

        return $this->render('admin/document_verify.html.twig', [
            'isValid' => $isValid,
            'message' => $message,
            'reference' => $reference,
            'type' => $type,
            'name' => $name,
            'issuedAt' => $issuedAt,
            'downloadUrl' => $isValid
                ? $this->generateUrl('app_admin_document_download', [
                    'ref' => $reference,
                    'type' => $type,
                    'name' => $name,
                    'issued' => $issued,
                    'sig' => $signature,
                ])
                : null,
        ]);
    }

    #[Route('/document/download', name: 'app_admin_document_download', methods: ['GET'])]
    public function downloadDocument(Request $request): Response
    {
        $reference = trim((string) $request->query->get('ref', ''));
        $type = trim((string) $request->query->get('type', ''));
        $name = trim((string) $request->query->get('name', ''));
        $issued = trim((string) $request->query->get('issued', ''));
        $signature = trim((string) $request->query->get('sig', ''));

        if ($reference === '' || $type === '' || $name === '' || !ctype_digit($issued) || $signature === '') {
            return new Response('Lien de telechargement invalide.', Response::HTTP_BAD_REQUEST);
        }

        $expectedSignature = $this->createDocumentSignature($reference, $type, $name, $issued);
        if (!hash_equals($expectedSignature, $signature)) {
            return new Response('Signature invalide.', Response::HTTP_FORBIDDEN);
        }

        if (!$this->isValidReference($reference)) {
            return new Response('Reference invalide.', Response::HTTP_BAD_REQUEST);
        }

        $pdfPath = $this->getGeneratedPdfPath($reference);
        if (!is_file($pdfPath)) {
            return new Response('Document introuvable. Regenerer le PDF depuis le backoffice.', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($pdfPath);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('document_%s.pdf', $reference)
        );
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
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
            ->createQueryBuilder('c');

        if ('' !== $search) {
            $qb
                ->leftJoin(User::class, 'u', 'WITH', 'u.id = c.id_utilisateur')
                ->leftJoin(OffreEmploi::class, 'o', 'WITH', 'o.idOffre = c.id_offre');

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
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ('' !== $statusFilter) {
            $qb
                ->andWhere('c.statut = :status')
                ->setParameter('status', $statusFilter);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        $qb
            ->orderBy('c.' . $sortBy, $sortOrder)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

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

        // ── QR Code ──────────────────────────────────────────────────
        $qrData = implode("\n", [
            'Candidature #' . $candidature->getIdCandidature(),
            'Candidat : ' . $user?->getPrenom() . ' ' . $user?->getNom(),
            'Offre     : ' . ($offre?->getTitre() ?? 'N/A'),
            'Statut    : ' . $candidature->getStatut(),
            'Date      : ' . $candidature->getDateCandidature()?->format('d/m/Y'),
        ]);

        $qrCode = new QrCode($qrData);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        $qrCodeBase64 = base64_encode($result->getString());
        // ─────────────────────────────────────────────────────────────
        return $this->render('admin/candidature/edit.html.twig', [
            'candidature' => $candidature,
            'adminName' => (string) $session->get('admin_user_name', 'Admin'),
            'user' => $user,
            'offre' => $offre,
            'fieldErrors' => $fieldErrors,
            'qrCodeBase64' => $qrCodeBase64,  // ← nouveau

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
            ->createQueryBuilder('c');

        if ('' !== $search) {
            $qb
                ->leftJoin(User::class, 'u', 'WITH', 'u.id = c.id_utilisateur')
                ->leftJoin(OffreEmploi::class, 'o', 'WITH', 'o.idOffre = c.id_offre');

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

        $qb->orderBy('c.' . $sortBy, $sortOrder)->setMaxResults(500);

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
            ->createQueryBuilder('p');

        // Ajouter la logique de recherche
        if ('' !== $search) {
            $qb->leftJoin(User::class, 'u', 'WITH', 'u.id = p.id_utilisateur');

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

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        $countQb = clone $qb;
        $totalPreferences = (int) $countQb
            ->select('COUNT(p.id_preference)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $qb
            ->orderBy($allowedSortFields[$sortBy], $sortOrder)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $totalPages = max(1, (int) ceil($totalPreferences / $limit));
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
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalPreferences' => $totalPreferences,
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
    // Dans AdminController.php — remplacer la méthode statistics()

    #[Route('/candidatures-preferences/statistics', name: 'app_admin_statistics', methods: ['GET'])]
    public function statistics(
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        MatchingService $matchingService,
        Request $request
    ): Response {
        $access = $this->requireAdmin($session);
        if ($access instanceof RedirectResponse) {
            return $access;
        }

        $conn = $entityManager->getConnection();

        // ── Filtre période ──────────────────────────────────────────────
        $periode = $request->query->get('periode', 'all'); // all | month | week
        $dateFilter = match ($periode) {
            'week' => (new \DateTime())->modify('-7 days')->format('Y-m-d'),
            'month' => (new \DateTime())->modify('-30 days')->format('Y-m-d'),
            default => null,
        };

        return $this->renderStatisticsDashboard($entityManager, $session, $matchingService, (string) $periode, $dateFilter);

        // ── Candidatures ────────────────────────────────────────────────
        $dateWhere = $dateFilter ? ' WHERE c.date_candidature >= :dateFilter' : '';
        $dateParams = $dateFilter ? ['dateFilter' => $dateFilter] : [];

        $totalCandidatures = (int) $conn
            ->executeQuery('SELECT COUNT(*) FROM candidature c' . $dateWhere, $dateParams)
            ->fetchOne();
        $candidatures = [];

        // Par statut
        $candidaturesByStatus = [];
        $candidaturesByNiveau = [];
        $candidaturesByDomaine = [];
        foreach ($candidatures as $c) {
            $s = $c->getStatut() ?: 'Non spécifié';
            $candidaturesByStatus[$s] = ($candidaturesByStatus[$s] ?? 0) + 1;
            $n = $c->getNiveauExperience() ?: 'Non spécifié';
            $candidaturesByNiveau[$n] = ($candidaturesByNiveau[$n] ?? 0) + 1;
            $d = $c->getDomaineExperience() ?: 'Non spécifié';
            $candidaturesByDomaine[$d] = ($candidaturesByDomaine[$d] ?? 0) + 1;
        }

        // Par offre
        $candidaturesByOffers = [];
        foreach ($candidatures as $c) {
            $offre = $entityManager->getRepository(OffreEmploi::class)->find($c->getIdOffre());
            if ($offre) {
                $t = $offre->getTitre();
                $candidaturesByOffers[$t] = ($candidaturesByOffers[$t] ?? 0) + 1;
            }
        }

        // Timeline — candidatures par jour sur les 14 derniers jours
        $candidaturesByStatus = $this->fetchCountMap(
            $conn->executeQuery(
                "SELECT COALESCE(NULLIF(c.statut, ''), 'Non spécifié') AS label, COUNT(*) AS total
                 FROM candidature c{$dateWhere}
                 GROUP BY label",
                $dateParams
            )->fetchAllAssociative()
        );
        $candidaturesByNiveau = $this->fetchCountMap(
            $conn->executeQuery(
                "SELECT COALESCE(NULLIF(c.niveau_experience, ''), 'Non spécifié') AS label, COUNT(*) AS total
                 FROM candidature c{$dateWhere}
                 GROUP BY label",
                $dateParams
            )->fetchAllAssociative()
        );
        $candidaturesByDomaine = $this->fetchCountMap(
            $conn->executeQuery(
                "SELECT COALESCE(NULLIF(c.domaine_experience, ''), 'Non spécifié') AS label, COUNT(*) AS total
                 FROM candidature c{$dateWhere}
                 GROUP BY label",
                $dateParams
            )->fetchAllAssociative()
        );
        $candidaturesByOffers = $this->fetchCountMap(
            $conn->executeQuery(
                "SELECT COALESCE(NULLIF(o.titre, ''), CONCAT('Offre #', c.id_offre), 'Non spécifié') AS label, COUNT(*) AS total
                 FROM candidature c
                 LEFT JOIN offre_emploi o ON o.id_offre = c.id_offre
                 {$dateWhere}
                 GROUP BY label
                 ORDER BY total DESC
                 LIMIT 8",
                $dateParams
            )->fetchAllAssociative()
        );

        $timeline = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = (new \DateTime())->modify("-{$i} days")->format('Y-m-d');
            $timeline[$day] = 0;
        }
        foreach ($candidatures as $c) {
            $d = $c->getDateCandidature()?->format('Y-m-d');
            if ($d && isset($timeline[$d])) {
                $timeline[$d]++;
            }
        }

        // ── Préférences ─────────────────────────────────────────────────
        $timelineRows = $conn->executeQuery(
            "SELECT DATE(c.date_candidature) AS day, COUNT(*) AS total
             FROM candidature c
             WHERE c.date_candidature >= :timelineStart
             GROUP BY day",
            ['timelineStart' => array_key_first($timeline)]
        )->fetchAllAssociative();
        foreach ($timelineRows as $row) {
            $day = (string) $row['day'];
            if (isset($timeline[$day])) {
                $timeline[$day] = (int) $row['total'];
            }
        }

        $preferences = $entityManager->getRepository(PreferenceCandidature::class)
            ->createQueryBuilder('p')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
        $totalPreferences = (int) $conn
            ->executeQuery('SELECT COUNT(*) FROM preference_candidature')
            ->fetchOne();
        $preferencesByType = [];
        $preferencesByMode = [];
        $preferencesByDisponibilite = [];
        $preferencesByContrat = [];
        $salairesByContrat = [];

        foreach ($preferences as $p) {
            $type = $p->getTypePosteSouhaite() ?: 'Non spécifié';
            $preferencesByType[$type] = ($preferencesByType[$type] ?? 0) + 1;
            $mode = $p->getModeTravail() ?: 'Non spécifié';
            $preferencesByMode[$mode] = ($preferencesByMode[$mode] ?? 0) + 1;
            $dispo = $p->getDisponibilite() ?: 'Non spécifié';
            $preferencesByDisponibilite[$dispo] = ($preferencesByDisponibilite[$dispo] ?? 0) + 1;
            $contrat = $p->getTypeContratSouhaite() ?: 'Non spécifié';
            $preferencesByContrat[$contrat] = ($preferencesByContrat[$contrat] ?? 0) + 1;
            if ($p->getPretentionSalariale() && $p->getTypeContratSouhaite()) {
                $salairesByContrat[$p->getTypeContratSouhaite()][] = $p->getPretentionSalariale();
            }
        }

        // Moyenne des prétentions salariales par contrat
        $avgSalairesByContrat = [];
        foreach ($salairesByContrat as $contrat => $salaires) {
            $avgSalairesByContrat[$contrat] = round(array_sum($salaires) / count($salaires));
        }

        // ── Matching Offres / Préférences ────────────────────────────────
        $preferencesByType = $this->fetchCountMap(
            $conn->executeQuery(
                "SELECT COALESCE(NULLIF(type_poste_souhaite, ''), 'Non spécifié') AS label, COUNT(*) AS total
                 FROM preference_candidature
                 GROUP BY label"
            )->fetchAllAssociative()
        );
        $preferencesByMode = $this->fetchCountMap(
            $conn->executeQuery(
                "SELECT COALESCE(NULLIF(mode_travail, ''), 'Non spécifié') AS label, COUNT(*) AS total
                 FROM preference_candidature
                 GROUP BY label"
            )->fetchAllAssociative()
        );
        $preferencesByDisponibilite = $this->fetchCountMap(
            $conn->executeQuery(
                "SELECT COALESCE(NULLIF(disponibilite, ''), 'Non spécifié') AS label, COUNT(*) AS total
                 FROM preference_candidature
                 GROUP BY label"
            )->fetchAllAssociative()
        );
        $preferencesByContrat = $this->fetchCountMap(
            $conn->executeQuery(
                "SELECT COALESCE(NULLIF(type_contrat_souhaite, ''), 'Non spécifié') AS label, COUNT(*) AS total
                 FROM preference_candidature
                 GROUP BY label"
            )->fetchAllAssociative()
        );
        $avgSalairesByContrat = $this->fetchCountMap(
            $conn->executeQuery(
                "SELECT type_contrat_souhaite AS label, ROUND(AVG(pretention_salariale)) AS total
                 FROM preference_candidature
                 WHERE pretention_salariale IS NOT NULL
                   AND type_contrat_souhaite IS NOT NULL
                   AND type_contrat_souhaite <> ''
                 GROUP BY type_contrat_souhaite"
            )->fetchAllAssociative()
        );

        $offres = $entityManager->getRepository(OffreEmploi::class)->findBy(['statutOffre' => 'OUVERTE'], null, 10);
        $matchingStats = [];
        foreach ($offres as $offre) {
            $scores = [];
            foreach ($preferences as $pref) {
                $result = $matchingService->calculateMatching($offre, $pref);
                $scores[] = $result['score'];
            }
            if (count($scores) > 0) {
                $avg = array_sum($scores) / count($scores);
                $matchingStats[] = [
                    'offre' => $offre->getTitre(),
                    'avgScore' => round($avg, 1),
                    'maxScore' => max($scores),
                    'candidatesMatched' => count(array_filter($scores, fn($s) => $s >= 70)),
                    'total' => count($scores),
                ];
            }
        }
        usort($matchingStats, fn($a, $b) => $b['avgScore'] <=> $a['avgScore']);

        // ── Données globales ─────────────────────────────────────────────
        $totalUsers = (int) $conn->executeQuery('SELECT COUNT(*) FROM utilisateur')->fetchOne();
        $totalOffers = (int) $conn->executeQuery('SELECT COUNT(*) FROM offre_emploi')->fetchOne();
        $rateOfCandidature = $totalUsers > 0 ? round(($totalCandidatures / $totalUsers) * 100, 1) : 0;
        $rateOfPreference = $totalUsers > 0 ? round(($totalPreferences / $totalUsers) * 100, 1) : 0;

        // Taux d'acceptation
        $accepted = $candidaturesByStatus['Accepté'] ?? 0;
        $refused = $candidaturesByStatus['Refusé'] ?? 0;
        $acceptanceRate = $totalCandidatures > 0 ? round(($accepted / $totalCandidatures) * 100, 1) : 0;

        return $this->render('admin/candidature/statistics.html.twig', [
            'adminName' => (string) $session->get('admin_user_name', 'Admin'),
            'periode' => $periode,
            // Candidatures
            'totalCandidatures' => $totalCandidatures,
            'candidaturesByStatus' => $candidaturesByStatus,
            'candidaturesByOffers' => $candidaturesByOffers,
            'candidaturesByNiveau' => $candidaturesByNiveau,
            'candidaturesByDomaine' => $candidaturesByDomaine,
            'timeline' => $timeline,
            'acceptanceRate' => $acceptanceRate,
            'maxStatus' => count($candidaturesByStatus) > 0 ? max($candidaturesByStatus) : 1,
            'maxOffers' => count($candidaturesByOffers) > 0 ? max($candidaturesByOffers) : 1,
            'maxNiveau' => count($candidaturesByNiveau) > 0 ? max($candidaturesByNiveau) : 1,
            'maxDomaine' => count($candidaturesByDomaine) > 0 ? max($candidaturesByDomaine) : 1,
            // Préférences
            'totalPreferences' => $totalPreferences,
            'preferencesByType' => $preferencesByType,
            'preferencesByMode' => $preferencesByMode,
            'preferencesByDisponibilite' => $preferencesByDisponibilite,
            'preferencesByContrat' => $preferencesByContrat,
            'avgSalairesByContrat' => $avgSalairesByContrat,
            'maxType' => count($preferencesByType) > 0 ? max($preferencesByType) : 1,
            'maxMode' => count($preferencesByMode) > 0 ? max($preferencesByMode) : 1,
            'maxDispo' => count($preferencesByDisponibilite) > 0 ? max($preferencesByDisponibilite) : 1,
            'maxContrat' => count($preferencesByContrat) > 0 ? max($preferencesByContrat) : 1,
            // Matching
            'matchingStats' => $matchingStats,
            // Global
            'totalUsers' => $totalUsers,
            'totalOffers' => $totalOffers,
            'rateOfCandidature' => $rateOfCandidature,
            'rateOfPreference' => $rateOfPreference,
        ]);
    }

    private function renderStatisticsDashboard(
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        MatchingService $matchingService,
        string $periode,
        ?string $dateFilter
    ): Response {
        $notSpecified = 'Non spécifié';

        $totalCandidatures = $this->countCandidatures($entityManager, $dateFilter);
        $candidaturesByStatus = $this->countCandidaturesBy($entityManager, 'c.statut', $dateFilter, $notSpecified, 20);
        $candidaturesByNiveau = $this->countCandidaturesBy($entityManager, 'c.niveau_experience', $dateFilter, $notSpecified, 20);
        $candidaturesByDomaine = $this->countCandidaturesBy($entityManager, 'c.domaine_experience', $dateFilter, $notSpecified, 20);
        $candidaturesByOffers = $this->countCandidaturesByOffer($entityManager, $dateFilter, $notSpecified);
        $timeline = $this->buildCandidatureTimeline($entityManager);

        $totalPreferences = $this->countPreferences($entityManager);
        $preferencesByType = $this->countPreferencesBy($entityManager, 'p.type_poste_souhaite', $notSpecified, 20);
        $preferencesByMode = $this->countPreferencesBy($entityManager, 'p.mode_travail', $notSpecified, 20);
        $preferencesByDisponibilite = $this->countPreferencesBy($entityManager, 'p.disponibilite', $notSpecified, 20);
        $preferencesByContrat = $this->countPreferencesBy($entityManager, 'p.type_contrat_souhaite', $notSpecified, 20);
        $avgSalairesByContrat = $this->averageSalaryByContract($entityManager);

        $preferences = $entityManager->getRepository(PreferenceCandidature::class)
            ->createQueryBuilder('p')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
        $offres = $entityManager->getRepository(OffreEmploi::class)->findBy(['statutOffre' => 'OUVERTE'], null, 10);
        $matchingStats = $this->buildMatchingStats($offres, $preferences, $matchingService);

        $totalUsers = $this->countUsers($entityManager);
        $totalOffers = $this->countOffers($entityManager);
        $rateOfCandidature = $totalUsers > 0 ? round(($totalCandidatures / $totalUsers) * 100, 1) : 0;
        $rateOfPreference = $totalUsers > 0 ? round(($totalPreferences / $totalUsers) * 100, 1) : 0;
        $accepted = $candidaturesByStatus['Accepté'] ?? 0;
        $acceptanceRate = $totalCandidatures > 0 ? round(($accepted / $totalCandidatures) * 100, 1) : 0;

        return $this->render('admin/candidature/statistics.html.twig', [
            'adminName' => (string) $session->get('admin_user_name', 'Admin'),
            'periode' => $periode,
            'totalCandidatures' => $totalCandidatures,
            'candidaturesByStatus' => $candidaturesByStatus,
            'candidaturesByOffers' => $candidaturesByOffers,
            'candidaturesByNiveau' => $candidaturesByNiveau,
            'candidaturesByDomaine' => $candidaturesByDomaine,
            'timeline' => $timeline,
            'acceptanceRate' => $acceptanceRate,
            'maxStatus' => count($candidaturesByStatus) > 0 ? max($candidaturesByStatus) : 1,
            'maxOffers' => count($candidaturesByOffers) > 0 ? max($candidaturesByOffers) : 1,
            'maxNiveau' => count($candidaturesByNiveau) > 0 ? max($candidaturesByNiveau) : 1,
            'maxDomaine' => count($candidaturesByDomaine) > 0 ? max($candidaturesByDomaine) : 1,
            'totalPreferences' => $totalPreferences,
            'preferencesByType' => $preferencesByType,
            'preferencesByMode' => $preferencesByMode,
            'preferencesByDisponibilite' => $preferencesByDisponibilite,
            'preferencesByContrat' => $preferencesByContrat,
            'avgSalairesByContrat' => $avgSalairesByContrat,
            'maxType' => count($preferencesByType) > 0 ? max($preferencesByType) : 1,
            'maxMode' => count($preferencesByMode) > 0 ? max($preferencesByMode) : 1,
            'maxDispo' => count($preferencesByDisponibilite) > 0 ? max($preferencesByDisponibilite) : 1,
            'maxContrat' => count($preferencesByContrat) > 0 ? max($preferencesByContrat) : 1,
            'matchingStats' => $matchingStats,
            'totalUsers' => $totalUsers,
            'totalOffers' => $totalOffers,
            'rateOfCandidature' => $rateOfCandidature,
            'rateOfPreference' => $rateOfPreference,
        ]);
    }

    private function countCandidatures(EntityManagerInterface $entityManager, ?string $dateFilter): int
    {
        return (int) $entityManager->createQuery(
            'SELECT COUNT(c.id_candidature)
             FROM App\Entity\Candidature c
             WHERE (:dateFilter IS NULL OR c.date_candidature >= :dateFilter)'
        )
            ->setParameter('dateFilter', $dateFilter)
            ->getSingleScalarResult();
    }

    private function countPreferences(EntityManagerInterface $entityManager): int
    {
        return (int) $entityManager->createQuery(
            'SELECT COUNT(p.id_preference)
             FROM App\Entity\PreferenceCandidature p'
        )->getSingleScalarResult();
    }

    private function countUsers(EntityManagerInterface $entityManager): int
    {
        return (int) $entityManager->createQuery(
            'SELECT COUNT(u.id)
             FROM App\Entity\User u'
        )->getSingleScalarResult();
    }

    private function countOffers(EntityManagerInterface $entityManager): int
    {
        return (int) $entityManager->createQuery(
            'SELECT COUNT(o.idOffre)
             FROM App\Entity\OffreEmploi o'
        )->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    private function countCandidaturesBy(
        EntityManagerInterface $entityManager,
        string $field,
        ?string $dateFilter,
        string $fallback,
        int $limit
    ): array {
        $query = sprintf(
            'SELECT NEW %s(%s, COUNT(c.id_candidature))
             FROM App\Entity\Candidature c
             WHERE (:dateFilter IS NULL OR c.date_candidature >= :dateFilter)
             GROUP BY %s',
            CountStatDto::class,
            $field,
            $field
        );

        return $this->statDtosToMap(
            $entityManager->createQuery($query)
                ->setParameter('dateFilter', $dateFilter)
                ->setMaxResults($limit)
                ->getResult(),
            $fallback
        );
    }

    /**
     * @return array<string, int>
     */
    private function countPreferencesBy(EntityManagerInterface $entityManager, string $field, string $fallback, int $limit): array
    {
        $query = sprintf(
            'SELECT NEW %s(%s, COUNT(p.id_preference))
             FROM App\Entity\PreferenceCandidature p
             GROUP BY %s',
            CountStatDto::class,
            $field,
            $field
        );

        return $this->statDtosToMap(
            $entityManager->createQuery($query)
                ->setMaxResults($limit)
                ->getResult(),
            $fallback
        );
    }

    /**
     * @return array<string, int>
     */
    private function countCandidaturesByOffer(
        EntityManagerInterface $entityManager,
        ?string $dateFilter,
        string $fallback
    ): array {
        $conn = $entityManager->getConnection();

        $sql = 'SELECT o.titre AS label, COUNT(c.id_candidature) AS total
            FROM candidature c
            LEFT JOIN offre_emploi o ON o.id_offre = c.id_offre
            WHERE (:dateFilter IS NULL OR c.date_candidature >= :dateFilter)
            GROUP BY o.titre
            ORDER BY total DESC
            LIMIT 8';

        $rows = $conn->executeQuery($sql, ['dateFilter' => $dateFilter])->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $counts[$label !== '' ? $label : $fallback] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function averageSalaryByContract(EntityManagerInterface $entityManager): array
    {
        $query = sprintf(
            'SELECT NEW %s(p.type_contrat_souhaite, AVG(p.pretention_salariale))
             FROM App\Entity\PreferenceCandidature p
             WHERE p.pretention_salariale IS NOT NULL
               AND p.type_contrat_souhaite IS NOT NULL
               AND p.type_contrat_souhaite <> :empty
             GROUP BY p.type_contrat_souhaite',
            CountStatDto::class
        );

        return $this->statDtosToMap(
            $entityManager->createQuery($query)
                ->setParameter('empty', '')
                ->setMaxResults(20)
                ->getResult(),
            'Non spécifié'
        );
    }

    /**
     * @return array<string, int>
     */
    private function buildCandidatureTimeline(EntityManagerInterface $entityManager): array
    {
        $timeline = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = (new \DateTimeImmutable())->modify("-{$i} days")->format('Y-m-d');
            $timeline[$day] = 0;
        }

        $query = sprintf(
            'SELECT NEW %s(c.date_candidature, COUNT(c.id_candidature))
             FROM App\Entity\Candidature c
             WHERE c.date_candidature >= :startDate
             GROUP BY c.date_candidature
             ORDER BY c.date_candidature ASC',
            CountStatDto::class
        );

        /** @var CountStatDto[] $rows */
        $rows = $entityManager->createQuery($query)
            ->setParameter('startDate', array_key_first($timeline))
            ->setMaxResults(14)
            ->getResult();

        foreach ($rows as $row) {
            if ($row->label !== null && isset($timeline[$row->label])) {
                $timeline[$row->label] = (int) $row->total;
            }
        }

        return $timeline;
    }

    /**
     * @param array<int, OffreEmploi> $offres
     * @param array<int, PreferenceCandidature> $preferences
     * @return array<int, array<string, mixed>>
     */
    private function buildMatchingStats(array $offres, array $preferences, MatchingService $matchingService): array
    {
        $matchingStats = [];
        foreach ($offres as $offre) {
            $scores = [];
            foreach ($preferences as $pref) {
                $result = $matchingService->calculateMatching($offre, $pref);
                $scores[] = $result['score'];
            }

            if (count($scores) > 0) {
                $avg = array_sum($scores) / count($scores);
                $matchingStats[] = [
                    'offre' => $offre->getTitre(),
                    'avgScore' => round($avg, 1),
                    'maxScore' => max($scores),
                    'candidatesMatched' => count(array_filter($scores, static fn($score): bool => $score >= 70)),
                    'total' => count($scores),
                ];
            }
        }

        usort($matchingStats, static fn(array $a, array $b): int => $b['avgScore'] <=> $a['avgScore']);

        return $matchingStats;
    }

    /**
     * @param array<int, CountStatDto> $rows
     * @return array<string, int>
     */
    private function statDtosToMap(array $rows, string $fallback): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row->label ?? ''));
            $counts[$label !== '' ? $label : $fallback] = (int) round((float) $row->total);
        }

        return $counts;
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

    private function createDocumentSignature(string $reference, string $type, string $name, string $issued): string
    {
        $secret = (string) $this->getParameter('kernel.secret');
        $payload = implode('|', [$reference, $type, $name, $issued]);

        return hash_hmac('sha256', $payload, $secret);
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

    private function isValidReference(string $reference): bool
    {
        return (bool) preg_match('/^[A-Z0-9\-]+$/', $reference);
    }

    private function getGeneratedPdfPath(string $reference): string
    {
        return $this->getParameter('kernel.project_dir') . '/var/generated_pdfs/' . $reference . '.pdf';
    }

    /**
     * @param array<int, array{label: mixed, total: mixed}> $rows
     * @return array<string, int>
     */
    private function fetchCountMap(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $label = (string) ($row['label'] ?? 'Non spécifié');
            $counts[$label] = (int) ($row['total'] ?? 0);
        }

        return $counts;
    }
}

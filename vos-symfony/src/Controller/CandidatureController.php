<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\OffreEmploi;
use App\Entity\User;
use App\Form\CandidatureType;
use App\Repository\CandidatureRepository;
use App\Service\EmailService;
use App\Service\PdfService;
use App\Service\CVAnalysisService;
use Doctrine\DBAL\Connection;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/client/candidature')]
final class CandidatureController extends AbstractController
{
    // ── Liste des candidatures du client ──────────────────────────────
   #[Route('', name: 'app_client_candidatures', methods: ['GET'])]
public function index(
    CandidatureRepository $repo,
    EntityManagerInterface $entityManager,
    SessionInterface $session,
    Request $request
): Response {
    $idUtilisateur = (int) $session->get('user_id', 0);

    if ($idUtilisateur <= 0) {
        return $this->redirectToRoute('app_signin');
    }

    // ── Filtres de recherche avancée ──────────────────────────────
    $filtreStatut   = trim((string) $request->query->get('statut', ''));
    $filtreNiveau   = trim((string) $request->query->get('niveau', ''));
    $filtreDomaine  = trim((string) $request->query->get('domaine', ''));
    $filtreContrat  = trim((string) $request->query->get('contrat', ''));
    $filtreDateDu   = trim((string) $request->query->get('date_du', ''));
    $filtreDateAu   = trim((string) $request->query->get('date_au', ''));
    $filtreMotCle   = trim((string) $request->query->get('mot_cle', ''));
    // ─────────────────────────────────────────────────────────────

    $qb = $entityManager->getRepository(Candidature::class)
        ->createQueryBuilder('c')
        ->where('c.id_utilisateur = :uid')
        ->setParameter('uid', $idUtilisateur)
        ->orderBy('c.date_candidature', 'DESC');

    // Filtre statut
    if ($filtreStatut !== '') {
        $qb->andWhere('c.statut = :statut')
           ->setParameter('statut', $filtreStatut);
    }

    // Filtre niveau d'expérience
    if ($filtreNiveau !== '') {
        $qb->andWhere('c.niveau_experience = :niveau')
           ->setParameter('niveau', $filtreNiveau);
    }

    // Filtre domaine
    if ($filtreDomaine !== '') {
        $qb->andWhere('LOWER(c.domaine_experience) LIKE :domaine')
           ->setParameter('domaine', '%' . mb_strtolower($filtreDomaine) . '%');
    }

    // Filtre type contrat (via l'offre)
    if ($filtreContrat !== '') {
        $qb->join(OffreEmploi::class, 'o', 'WITH', 'o.idOffre = c.id_offre')
           ->andWhere('o.typeContrat = :contrat')
           ->setParameter('contrat', $filtreContrat);
    }

    // Filtre date du
    if ($filtreDateDu !== '') {
        try {
            $qb->andWhere('c.date_candidature >= :date_du')
               ->setParameter('date_du', new \DateTime($filtreDateDu));
        } catch (\Throwable) {}
    }

    // Filtre date au
    if ($filtreDateAu !== '') {
        try {
            $qb->andWhere('c.date_candidature <= :date_au')
               ->setParameter('date_au', new \DateTime($filtreDateAu));
        } catch (\Throwable) {}
    }

    // Filtre mot clé (message ou dernier poste)
    if ($filtreMotCle !== '') {
        $qb->andWhere(
            $qb->expr()->orX(
                'LOWER(c.message_candidat) LIKE :mot_cle',
                'LOWER(c.dernier_poste) LIKE :mot_cle'
            )
        )->setParameter('mot_cle', '%' . mb_strtolower($filtreMotCle) . '%');
    }

    $candidatures = $qb->getQuery()->getResult();
    $offreTitles  = $this->getOffreTitles($entityManager, $candidatures);

    return $this->render('client/candidature/index.html.twig', [
        'candidatures' => $candidatures,
        'offreTitles'  => $offreTitles,
        'userName'     => $session->get('user_name', 'Utilisateur'),
        // Renvoyer les filtres pour les garder affichés
        'filtreStatut'  => $filtreStatut,
        'filtreNiveau'  => $filtreNiveau,
        'filtreDomaine' => $filtreDomaine,
        'filtreContrat' => $filtreContrat,
        'filtreDateDu'  => $filtreDateDu,
        'filtreDateAu'  => $filtreDateAu,
        'filtreMotCle'  => $filtreMotCle,
    ]);
}

    // ── Formulaire d'ajout ────────────────────────────────────────────
    #[Route('/new/{id_offre}', name: 'app_client_candidature_new', methods: ['GET', 'POST'])]
    public function new(
        int $id_offre,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        SessionInterface $session,
        EmailService $emailService,
        CVAnalysisService $cvAnalysisService
    ): Response {
        $idUtilisateur = (int) $session->get('user_id', 0);

        if ($idUtilisateur <= 0) {
            return $this->redirectToRoute('app_signin');
        }

        $offre = $entityManager->getRepository(OffreEmploi::class)->find($id_offre);
        if (!$offre) {
            $this->addFlash('error', 'Offre d\'emploi introuvable.');
            return $this->redirectToRoute('client_opportunites');
        }

        $candidature = new Candidature();
        $candidature
            ->setIdOffre($id_offre)
            ->setIdUtilisateur($idUtilisateur)
            ->setStatut('En attente')
            ->setDateCandidature(new \DateTime('today'));

        $form = $this->createForm(CandidatureType::class, $candidature, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $cvFile = $form->get('cv')->getData();
            $lettreFile = $form->get('lettre_motivation')->getData();

            $this->addPdfFieldValidationError($form->get('cv'), $cvFile, true, 'Le CV');
            $this->addPdfFieldValidationError($form->get('lettre_motivation'), $lettreFile, true, 'La lettre de motivation');

            if ($form->isValid()) {
                if ($cvFile instanceof UploadedFile) {
                    $candidature->setCv('uploads/cv/' . $this->uploadFile($cvFile, $slugger, 'cv'));
                }

                if ($lettreFile instanceof UploadedFile) {
                    $candidature->setLettreMotivation('uploads/lettres/' . $this->uploadFile($lettreFile, $slugger, 'lettres'));
                }

                $entityManager->persist($candidature);
                $entityManager->flush();

                try {
                    $user = $entityManager->getRepository(User::class)->find($idUtilisateur);
                    if ($user) {
                        // Email au candidat
                        $emailService->sendCandidatureCreatedEmail($candidature, $user, $offre->getTitre());
                        // Notification aux admins
                        $emailService->notifyAdminsNewCandidature($candidature, $user, $offre->getTitre());
                    }
                    $cvPath = $candidature->getCv();
                    if ($cvPath) {
                        $projectDir = $this->getParameter('kernel.project_dir');
                        $fullPath = $projectDir . '/public/' . $cvPath;
                        if (file_exists($fullPath)) {
                            $parser = new \Smalot\PdfParser\Parser();
                            $pdf = $parser->parseFile($fullPath);
                            $cvText = $pdf->getText();
                            if (!empty(trim($cvText))) {
                                $cvText = mb_convert_encoding($cvText, 'UTF-8', 'UTF-8');
                                $cvText = iconv('UTF-8', 'UTF-8//IGNORE', $cvText);
                                $cvAnalysisService->analyzerCV($cvText, $candidature);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Logger l'erreur mais ne pas bloquer le processus
                    $this->addFlash('warning', 'Candidature soumise, mais l\'email de confirmation n\'a pas pu être envoyé.');
                }

                $this->addFlash('success', 'Votre candidature a été soumise avec succès !');
                return $this->redirectToRoute('app_client_candidatures');
            }
        }

        return $this->render('client/candidature/new.html.twig', [
            'form' => $form->createView(),
            'offreTitre' => $offre->getTitre(),
            'id_offre' => $id_offre,
            'userName' => $session->get('user_name', 'Utilisateur'),
        ]);
    }

    // ── Formulaire d'édition ──────────────────────────────────────────
    #[Route('/{id_candidature}/edit', name: 'app_client_candidature_edit', methods: ['GET', 'POST'])]
    public function edit(
        Candidature $candidature,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        SessionInterface $session,
        EmailService $emailService,
        CVAnalysisService $cvAnalysisService

    ): Response {
        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0 || $candidature->getIdUtilisateur() !== $idUtilisateur) {
            return $this->redirectToRoute('app_signin');
        }

        $offre = $entityManager->getRepository(OffreEmploi::class)->find($candidature->getIdOffre());

        $form = $this->createForm(CandidatureType::class, $candidature, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $cvFile = $form->get('cv')->getData();
            $lettreFile = $form->get('lettre_motivation')->getData();

            $this->addPdfFieldValidationError($form->get('cv'), $cvFile, false, 'Le CV');
            $this->addPdfFieldValidationError($form->get('lettre_motivation'), $lettreFile, false, 'La lettre de motivation');

            if ($form->isValid()) {
                if ($cvFile instanceof UploadedFile) {
                    $candidature->setCv('uploads/cv/' . $this->uploadFile($cvFile, $slugger, 'cv'));
                }

                if ($lettreFile instanceof UploadedFile) {
                    $candidature->setLettreMotivation('uploads/lettres/' . $this->uploadFile($lettreFile, $slugger, 'lettres'));
                }

                $entityManager->flush();


                try {
                    $user = $entityManager->getRepository(User::class)->find($idUtilisateur);
                    if ($user && $offre) {
                        // Email au candidat
                        $emailService->sendCandidatureUpdatedEmail($candidature, $user, $offre->getTitre());
                        // Notification aux admins
                        $emailService->notifyAdminsUpdatedCandidature($candidature, $user, $offre->getTitre());
                    }
                    if ($cvFile instanceof UploadedFile) { // seulement si nouveau CV uploadé
                        $cvPath = $candidature->getCv();
                        if ($cvPath) {
                            $projectDir = $this->getParameter('kernel.project_dir');
                            $fullPath = $projectDir . '/public/' . $cvPath;
                            if (file_exists($fullPath)) {
                                $parser = new \Smalot\PdfParser\Parser();
                                $pdf = $parser->parseFile($fullPath);
                                $cvText = $pdf->getText();
                                if (!empty(trim($cvText))) {
                                    $cvText = mb_convert_encoding($cvText, 'UTF-8', 'UTF-8');
                                    $cvText = iconv('UTF-8', 'UTF-8//IGNORE', $cvText);
                                    $cvAnalysisService->analyzerCV($cvText, $candidature);
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Logger l'erreur mais ne pas bloquer le processus
                    $this->addFlash('warning', 'Candidature mise à jour, mais l\'email de confirmation n\'a pas pu être envoyé.');
                }

                $this->addFlash('success', 'Candidature mise à jour avec succès !');
                return $this->redirectToRoute('app_client_candidatures');
            }
        }

        return $this->render('client/candidature/edit.html.twig', [
            'form' => $form->createView(),
            'candidature' => $candidature,
            'offreTitre' => $offre?->getTitre(),
            'userName' => $session->get('user_name', 'Utilisateur'),
        ]);
    }

    // ── Détails d'une candidature ─────────────────────────────────────
    #[Route('/{id_candidature}/detail', name: 'app_client_candidature_detail', methods: ['GET'])]
    public function detail(
        Candidature $candidature,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0 || $candidature->getIdUtilisateur() !== $idUtilisateur) {
            return $this->redirectToRoute('app_signin');
        }

        $offre = $entityManager->getRepository(OffreEmploi::class)->find($candidature->getIdOffre());
        $user = $entityManager->getRepository(User::class)->find($idUtilisateur);

        // ── QR Code ──────────────────────────────────────────────────
        $qrData = implode("\n", [
            'Candidature #' . $candidature->getIdCandidature(),
            'Candidat : ' . $user?->getPrenom() . ' ' . $user?->getNom(),
            'Offre     : ' . ($offre?->getTitre() ?? 'N/A'),
            'Statut    : ' . $candidature->getStatut(),
            'Date      : ' . $candidature->getDateCandidature()?->format('d/m/Y'),
            'Domaine   : ' . ($candidature->getDomaineExperience() ?? 'N/A'),
            'Niveau    : ' . ($candidature->getNiveauExperience() ?? 'N/A'),
        ]);

        $qrCode = new QrCode($qrData);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        $qrCodeBase64 = base64_encode($result->getString());
        // ─────────────────────────────────────────────────────────────
        return $this->render('client/candidature/detail.html.twig', [
            'candidature' => $candidature,
            'offreTitre' => $offre?->getTitre() ?? 'Offre',
            'userName' => $session->get('user_name', 'Utilisateur'),
            'qrCodeBase64' => $qrCodeBase64,  // ← nouveau

        ]);
    }

    // ── Suppression ───────────────────────────────────────────────────
    #[Route('/{id_candidature}/delete', name: 'app_client_candidature_delete', methods: ['POST'])]
    public function delete(
        Candidature $candidature,
        Request $request,
        EntityManagerInterface $em,
        SessionInterface $session,
        EmailService $emailService
    ): Response {
        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0 || $candidature->getIdUtilisateur() !== $idUtilisateur) {
            return $this->redirectToRoute('app_signin');
        }

        if ($this->isCsrfTokenValid('delete' . $candidature->getIdCandidature(), $request->request->get('_token'))) {
            // Récupérer les informations avant suppression
            $user = $em->getRepository(User::class)->find($idUtilisateur);
            $offre = $em->getRepository(OffreEmploi::class)->find($candidature->getIdOffre());

            // Récupérer infos pour l'email avant la suppression
            $userEmail = $user?->getEmail() ?? '';
            $userName = $user ? ($user->getPrenom() . ' ' . $user->getNom()) : 'Utilisateur';
            $offreTitre = $offre?->getTitre() ?? 'Offre';

            $em->remove($candidature);
            $em->flush();

            // Envoyer les emails de notification
            try {
                if ($user && $userEmail) {
                    // Email au candidat
                    $emailService->sendCandidatureDeletedEmail($userEmail, $userName, $offreTitre);
                    // Notification aux admins
                    $emailService->notifyAdminsDeletedCandidature($userName, $userEmail, $offreTitre);
                }
            } catch (\Exception $e) {
                // Logger l'erreur mais ne pas bloquer le processus
                $this->addFlash('warning', 'Candidature supprimée, mais l\'email de confirmation n\'a pas pu être envoyé.');
            }

            $this->addFlash('success', 'Candidature supprimée.');
        }

        return $this->redirectToRoute('app_client_candidatures');
    }

    // ── Export PDF - Liste des candidatures ───────────────────────────
    #[Route('/export-pdf', name: 'app_client_candidature_export_pdf', methods: ['GET'])]
    public function exportListPdf(
        CandidatureRepository $repo,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        PdfService $pdfService
    ): Response {
        $idUtilisateur = (int) $session->get('user_id', 0);

        if ($idUtilisateur <= 0) {
            return $this->redirectToRoute('app_signin');
        }

        $candidatures = $repo->findBy(
            ['id_utilisateur' => $idUtilisateur],
            ['date_candidature' => 'DESC']
        );

        $offreTitles = $this->getOffreTitles($entityManager, $candidatures);

        $html = $this->renderView('pdf/candidatures_list_client.html.twig', [
            'candidatures' => $candidatures,
            'offreTitles' => $offreTitles,
        ]);

        $filename = 'mes_candidatures_' . date('d-m-Y') . '.pdf';

        return $pdfService->generatePdfResponse($html, $filename);
    }

    // ── Export PDF - Détail d'une candidature ────────────────────────
    #[Route('/{id_candidature}/export-pdf', name: 'app_client_candidature_detail_pdf', methods: ['GET'])]
    public function exportDetailPdf(
        Candidature $candidature,
        EntityManagerInterface $entityManager,
        SessionInterface $session,
        PdfService $pdfService
    ): Response {
        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0 || $candidature->getIdUtilisateur() !== $idUtilisateur) {
            return $this->redirectToRoute('app_signin');
        }

        $candidat = $entityManager->getRepository(User::class)->find($idUtilisateur);
        $offre = $entityManager->getRepository(OffreEmploi::class)->find($candidature->getIdOffre());

        $html = $this->renderView('pdf/candidature_detail.html.twig', [
            'candidature' => $candidature,
            'candidat' => $candidat,
            'offre' => $offre,
        ]);

        $filename = 'candidature_' . $candidature->getIdCandidature() . '_' . date('d-m-Y') . '.pdf';

        return $pdfService->generatePdfResponse($html, $filename);
    }

    // ── Helper methods ────────────────────────────────────────────────
    private function getOffreTitles(EntityManagerInterface $entityManager, array $candidatures): array
    {
        $offreIds = array_values(array_unique(array_filter(array_map(
            static fn(Candidature $candidature): ?int => $candidature->getIdOffre(),
            $candidatures
        ))));

        if ($offreIds === []) {
            return [];
        }

        $query = sprintf('SELECT id_offre, titre FROM offre_emploi WHERE id_offre IN (%s)', implode(',', $offreIds));
        $rows = $entityManager->getConnection()->executeQuery($query)->fetchAllAssociative();

        $titles = [];
        foreach ($rows as $row) {
            $titles[(int) $row['id_offre']] = (string) $row['titre'];
        }

        return $titles;
    }

    private function normalizeText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function userExists(EntityManagerInterface $entityManager, int $userId): bool
    {
        $result = $entityManager->getConnection()->executeQuery(
            'SELECT id_utilisateur FROM utilisateur WHERE id_utilisateur = :id LIMIT 1',
            ['id' => $userId]
        )->fetchOne();

        return $result !== false;
    }

    private function uploadFile($file, SluggerInterface $slugger, string $folder): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $extension = strtolower((string) pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = 'bin';
        }
        $newFilename = time() . '_' . $safeFilename . '.' . $extension;

        $file->move(
            $this->getParameter('kernel.project_dir') . '/public/uploads/' . $folder,
            $newFilename
        );

        return $newFilename;
    }

    private function addPdfFieldValidationError(mixed $formField, mixed $file, bool $required, string $label): void
    {
        if (!$file instanceof UploadedFile) {
            if ($required) {
                $formField->addError(new FormError($label . ' est obligatoire.'));
            }

            return;
        }

        $maxSize = 5 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            $formField->addError(new FormError($label . ' doit faire au maximum 5 MB.'));
            return;
        }

        $extension = strtolower((string) pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            $formField->addError(new FormError($label . ' doit être un fichier PDF (.pdf).'));
        }
    }
}

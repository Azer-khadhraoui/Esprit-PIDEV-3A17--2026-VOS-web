<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\OffreEmploi;
use App\Form\CandidatureType;
use App\Repository\CandidatureRepository;
use Doctrine\DBAL\Connection;
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
        SessionInterface $session
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

        return $this->render('client/candidature/index.html.twig', [
            'candidatures' => $candidatures,
            'offreTitles' => $offreTitles,
            'userName' => $session->get('user_name', 'Utilisateur'),
        ]);
    }

    // ── Formulaire d'ajout ────────────────────────────────────────────
    #[Route('/new/{id_offre}', name: 'app_client_candidature_new', methods: ['GET', 'POST'])]
    public function new(
        int $id_offre,
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        SessionInterface $session
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
        SessionInterface $session
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

        return $this->render('client/candidature/detail.html.twig', [
            'candidature' => $candidature,
            'offreTitre' => $offre?->getTitre() ?? 'Offre',
            'userName' => $session->get('user_name', 'Utilisateur'),
        ]);
    }

    // ── Suppression ───────────────────────────────────────────────────
    #[Route('/{id_candidature}/delete', name: 'app_client_candidature_delete', methods: ['POST'])]
    public function delete(
        Candidature $candidature,
        Request $request,
        EntityManagerInterface $em,
        SessionInterface $session
    ): Response {
        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0 || $candidature->getIdUtilisateur() !== $idUtilisateur) {
            return $this->redirectToRoute('app_signin');
        }

        if ($this->isCsrfTokenValid('delete'.$candidature->getIdCandidature(), $request->request->get('_token'))) {
            $em->remove($candidature);
            $em->flush();
            $this->addFlash('success', 'Candidature supprimée.');
        }

        return $this->redirectToRoute('app_client_candidatures');
    }

    // ── Helper methods ────────────────────────────────────────────────
    private function getOffreTitles(EntityManagerInterface $entityManager, array $candidatures): array
    {
        $offreIds = array_values(array_unique(array_filter(array_map(
            static fn (Candidature $candidature): ?int => $candidature->getIdOffre(),
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
        $safeFilename     = $slugger->slug($originalFilename);
        $extension        = strtolower((string) pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = 'bin';
        }
        $newFilename      = time() . '_' . $safeFilename . '.' . $extension;

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

<?php

namespace App\Controller;

use App\Entity\PreferenceCandidature;
use App\Form\PreferenceCandidatureType;
use App\Repository\PreferenceCandidatureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/client/preference')]
final class PreferenceCandidatureController extends AbstractController
{
    // ── Liste des préférences du client ───────────────────────────────
  #[Route('', name: 'app_client_preferences', methods: ['GET'])]
public function index(
    EntityManagerInterface $entityManager,
    SessionInterface $session,
    Request $request
): Response {
    $idUtilisateur = (int) $session->get('user_id', 0);

    if ($idUtilisateur <= 0) {
        return $this->redirectToRoute('app_signin');
    }

    // ── Filtres ───────────────────────────────────────────────────
    $filtrePoste       = trim((string) $request->query->get('poste', ''));
    $filtreMode        = trim((string) $request->query->get('mode', ''));
    $filtreContrat     = trim((string) $request->query->get('contrat', ''));
    $filtreDisponible  = trim((string) $request->query->get('disponibilite', ''));
    $filtreSalaireMin  = $request->query->get('salaire_min', '');
    $filtreSalaireMax  = $request->query->get('salaire_max', '');
    // ─────────────────────────────────────────────────────────────

    $qb = $entityManager->getRepository(PreferenceCandidature::class)
        ->createQueryBuilder('p')
        ->where('p.id_utilisateur = :uid')
        ->setParameter('uid', $idUtilisateur)
        ->orderBy('p.id_preference', 'DESC');

    if ($filtrePoste !== '') {
        $qb->andWhere('LOWER(p.type_poste_souhaite) LIKE :poste')
           ->setParameter('poste', '%' . mb_strtolower($filtrePoste) . '%');
    }

    if ($filtreMode !== '') {
        $qb->andWhere('p.mode_travail = :mode')
           ->setParameter('mode', $filtreMode);
    }

    if ($filtreContrat !== '') {
        $qb->andWhere('p.type_contrat_souhaite = :contrat')
           ->setParameter('contrat', $filtreContrat);
    }

    if ($filtreDisponible !== '') {
        $qb->andWhere('p.disponibilite = :dispo')
           ->setParameter('dispo', $filtreDisponible);
    }

    if ($filtreSalaireMin !== '') {
        $qb->andWhere('p.pretention_salariale >= :sal_min')
           ->setParameter('sal_min', (float) $filtreSalaireMin);
    }

    if ($filtreSalaireMax !== '') {
        $qb->andWhere('p.pretention_salariale <= :sal_max')
           ->setParameter('sal_max', (float) $filtreSalaireMax);
    }

    $preferences = $qb->getQuery()->getResult();

    return $this->render('client/preferenceCandidature/listPreference.html.twig', [
        'preferences'      => $preferences,
        'userName'         => $session->get('user_name', 'Utilisateur'),
        'filtrePoste'      => $filtrePoste,
        'filtreMode'       => $filtreMode,
        'filtreContrat'    => $filtreContrat,
        'filtreDisponible' => $filtreDisponible,
        'filtreSalaireMin' => $filtreSalaireMin,
        'filtreSalaireMax' => $filtreSalaireMax,
    ]);
}

    // ── Formulaire d'ajout ────────────────────────────────────────────
    #[Route('/new', name: 'app_client_preference_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        $idUtilisateur = (int) $session->get('user_id', 0);

        if ($idUtilisateur <= 0) {
            return $this->redirectToRoute('app_signin');
        }

        $preference = new PreferenceCandidature();
        $preference->setIdUtilisateur($idUtilisateur);

        $form = $this->createForm(PreferenceCandidatureType::class, $preference);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($preference);
            $entityManager->flush();

            $this->addFlash('success', 'Préférence créée avec succès !');
            return $this->redirectToRoute('app_client_preferences');
        }

        return $this->render('client/preferenceCandidature/newPreference.html.twig', [
            'form' => $form->createView(),
            'userName' => $session->get('user_name', 'Utilisateur'),
        ]);
    }

    // ── Formulaire d'édition ──────────────────────────────────────────
    #[Route('/{id_preference}/edit', name: 'app_client_preference_edit', methods: ['GET', 'POST'])]
    public function edit(
        PreferenceCandidature $preference,
        Request $request,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {
        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0 || $preference->getIdUtilisateur() !== $idUtilisateur) {
            return $this->redirectToRoute('app_signin');
        }

        $form = $this->createForm(PreferenceCandidatureType::class, $preference);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Préférence mise à jour avec succès !');
            return $this->redirectToRoute('app_client_preferences');
        }

        return $this->render('client/preferenceCandidature/editPrefernce.html.twig', [
            'form' => $form->createView(),
            'preference' => $preference,
            'userName' => $session->get('user_name', 'Utilisateur'),
        ]);
    }

    // ── Détails d'une préférence ──────────────────────────────────────
    #[Route('/{id_preference}/detail', name: 'app_client_preference_detail', methods: ['GET'])]
    public function detail(
        PreferenceCandidature $preference,
        SessionInterface $session
    ): Response {
        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0 || $preference->getIdUtilisateur() !== $idUtilisateur) {
            return $this->redirectToRoute('app_signin');
        }

        return $this->render('client/preferenceCandidature/detailPrefernce.html.twig', [
            'preference' => $preference,
            'userName' => $session->get('user_name', 'Utilisateur'),
        ]);
    }

    // ── Suppression ───────────────────────────────────────────────────
    #[Route('/{id_preference}/delete', name: 'app_client_preference_delete', methods: ['POST'])]
    public function delete(
        PreferenceCandidature $preference,
        Request $request,
        EntityManagerInterface $em,
        SessionInterface $session
    ): Response {
        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0 || $preference->getIdUtilisateur() !== $idUtilisateur) {
            return $this->redirectToRoute('app_signin');
        }

        if ($this->isCsrfTokenValid('delete'.$preference->getIdPreference(), $request->request->get('_token'))) {
            $em->remove($preference);
            $em->flush();
            $this->addFlash('success', 'Préférence supprimée.');
        }

        return $this->redirectToRoute('app_client_preferences');
    }

    // ── Helper methods ────────────────────────────────────────────────
    private function normalizeText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}

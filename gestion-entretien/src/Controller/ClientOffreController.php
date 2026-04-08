<?php

namespace App\Controller;

use App\Entity\OffreEmploi;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class ClientOffreController extends AbstractController
{
    #[Route('/opportunites', name: 'client_opportunites', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        // Vérifier que l'utilisateur est connecté
        $idUtilisateur = (int) $session->get('user_id', 0);
        if ($idUtilisateur <= 0) {
            return $this->redirectToRoute('app_signin');
        }

        $offreRepository = $entityManager->getRepository(OffreEmploi::class);
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 6;

        $search = $this->normalizeText($request->query->get('q'));
        $typeContrat = $this->normalizeText($request->query->get('type_contrat'));
        $workPreference = $this->normalizeText($request->query->get('work_preference'));
        $lieu = $this->normalizeText($request->query->get('lieu'));
        $statut = $this->normalizeText($request->query->get('statut'));

        $qb = $offreRepository->createQueryBuilder('o');

        if ($search !== null) {
            $qb
                ->andWhere('o.titre LIKE :q OR o.description LIKE :q OR o.lieu LIKE :q')
                ->setParameter('q', '%'.$search.'%');
        }

        if ($typeContrat !== null) {
            $qb
                ->andWhere('o.typeContrat = :typeContrat')
                ->setParameter('typeContrat', $typeContrat);
        }

        if ($workPreference !== null) {
            $qb
                ->andWhere('o.workPreference = :workPreference')
                ->setParameter('workPreference', $workPreference);
        }

        if ($lieu !== null) {
            $qb
                ->andWhere('o.lieu LIKE :lieu')
                ->setParameter('lieu', '%'.$lieu.'%');
        }

        if ($statut !== null) {
            $qb
                ->andWhere('o.statutOffre = :statut')
                ->setParameter('statut', $statut);
        } else {
            $qb
                ->andWhere('o.statutOffre IN (:openStatuts)')
                ->setParameter('openStatuts', ['ACTIVE', 'OUVERTE']);
        }

        $countQb = clone $qb;
        $totalOffers = (int) $countQb
            ->select('COUNT(o.idOffre)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalOffers / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $offers = $qb
            ->orderBy('o.datePublication', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $criteriaByOffer = $this->getLatestCriteriaByOffer($entityManager);
        $isClientAuthenticated = (bool) $session->get('user_id') && (string) $session->get('user_role', '') === 'CLIENT';

        return $this->render('client/opportunites.html.twig', [
            'offers' => $offers,
            'criteriaByOffer' => $criteriaByOffer,
            'isClientAuthenticated' => $isClientAuthenticated,
            'userName' => (string) $session->get('user_name', 'Utilisateur'),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $totalOffers,
                'totalPages' => $totalPages,
            ],
            'filters' => [
                'q' => $search,
                'type_contrat' => $typeContrat,
                'work_preference' => $workPreference,
                'lieu' => $lieu,
                'statut' => $statut,
                'page' => $page,
            ],
            'filterOptions' => [
                'typesContrat' => $this->getDistinctOffreValues($entityManager, 'type_contrat'),
                'workPreferences' => $this->getDistinctOffreValues($entityManager, 'work_preference'),
                'statuts' => $this->getDistinctOffreValues($entityManager, 'statut_offre'),
            ],
        ]);
    }

    #[Route('/opportunites/{idOffre}/postuler', name: 'client_postuler_offre', methods: ['POST'])]
    public function apply(
        #[MapEntity(id: 'idOffre')] OffreEmploi $offre,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('postuler_offre_'.$offre->getIdOffre(), $token)) {
            $this->addFlash('error', 'Token invalide. Veuillez reessayer.');

            return $this->redirectToRoute('client_opportunites');
        }

        $idUtilisateur = (int) $request->request->get('id_utilisateur');
        if ($idUtilisateur <= 0 || !$this->userExists($entityManager, $idUtilisateur)) {
            $this->addFlash('error', 'Utilisateur invalide. Entrez un ID utilisateur existant.');

            return $this->redirectToRoute('client_opportunites');
        }

        $statutOffre = $offre->getStatutOffre();
        if ($statutOffre === 'FERMEE' || $statutOffre === 'INACTIVE') {
            $this->addFlash('error', 'Cette offre n est plus ouverte aux candidatures.');

            return $this->redirectToRoute('client_opportunites');
        }

        $message = $this->normalizeText($request->request->get('message_candidat')) ?? 'Candidature soumise via la page opportunites.';

        $entityManager->getConnection()->insert('candidature', [
            'date_candidature' => (new \DateTime())->format('Y-m-d'),
            'statut' => 'En attente',
            'message_candidat' => $message,
            'cv' => null,
            'lettre_motivation' => null,
            'niveau_experience' => null,
            'annees_experience' => null,
            'domaine_experience' => null,
            'dernier_poste' => null,
            'id_utilisateur' => $idUtilisateur,
            'id_offre' => $offre->getIdOffre(),
        ]);

        $this->addFlash('success', 'Candidature envoyee avec succes.');

        return $this->redirectToRoute('client_opportunites');
    }

    private function normalizeText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function getDistinctOffreValues(EntityManagerInterface $entityManager, string $column): array
    {
        $allowedColumns = ['statut_offre', 'type_contrat', 'work_preference'];
        if (!in_array($column, $allowedColumns, true)) {
            return [];
        }

        $rows = $entityManager->getConnection()->executeQuery(
            sprintf("SELECT DISTINCT %s AS value FROM offre_emploi WHERE %s IS NOT NULL AND %s <> '' ORDER BY %s ASC", $column, $column, $column, $column)
        )->fetchFirstColumn();

        return array_values(array_map(static fn (mixed $value): string => (string) $value, $rows));
    }

    private function userExists(EntityManagerInterface $entityManager, int $userId): bool
    {
        $result = $entityManager->getConnection()->executeQuery(
            'SELECT id_utilisateur FROM utilisateur WHERE id_utilisateur = :id LIMIT 1',
            ['id' => $userId]
        )->fetchOne();

        return $result !== false;
    }

    /**
     * @return array<int, array{niveau_experience: ?string, niveau_etude: ?string, competences_requises: ?string, responsibilities: ?string}>
     */
    private function getLatestCriteriaByOffer(EntityManagerInterface $entityManager): array
    {
        $rows = $entityManager->getConnection()->executeQuery(
            'SELECT id_offre, id_critere, niveau_experience, niveau_etude, competences_requises, responsibilities FROM critere_offre ORDER BY id_offre ASC, id_critere DESC'
        )->fetchAllAssociative();

        $criteriaByOffer = [];
        foreach ($rows as $row) {
            $offreId = (int) $row['id_offre'];
            if (isset($criteriaByOffer[$offreId])) {
                continue;
            }

            $criteriaByOffer[$offreId] = [
                'niveau_experience' => isset($row['niveau_experience']) ? (string) $row['niveau_experience'] : null,
                'niveau_etude' => isset($row['niveau_etude']) ? (string) $row['niveau_etude'] : null,
                'competences_requises' => isset($row['competences_requises']) ? (string) $row['competences_requises'] : null,
                'responsibilities' => isset($row['responsibilities']) ? (string) $row['responsibilities'] : null,
            ];
        }

        return $criteriaByOffer;
    }
}
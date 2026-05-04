<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\ContratEmbauche;
use App\Repository\EntretienRepository;
use App\Repository\EvaluationEntretienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

#[Route('/candidat')]
class FrontController extends AbstractController
{
    #[Route('/', name: 'app_front_dashboard', methods: ['GET'])]
    public function dashboard(EntretienRepository $entretienRepository, EvaluationEntretienRepository $evaluationRepository, SessionInterface $session): Response
    {
        $userId = $this->requireClient($session);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $entretiens = $entretienRepository->findForUser($userId);
        $evaluations = $evaluationRepository->findForUser($userId);

        $totalEntretiens = count($entretiens);
        $termines = 0;
        $planifies = 0;

        foreach ($entretiens as $entretien) {
            $statut = strtolower($entretien->getStatutEntretien() ?? '');
            if (str_contains($statut, 'termin')) {
                $termines++;
            } elseif (str_contains($statut, 'planifi')) {
                $planifies++;
            }
        }

        $dernierEntretiens = array_slice(array_reverse($entretiens), 0, 5);
        $totalEvals = count($evaluations);
        $scoreMoyen = 0;
        $noteMoyenne = 0;
        $evalStats = [0, 0, 0, 0, 0];
        $derniereDecision = null;

        if ($totalEvals > 0) {
            foreach ($evaluations as $evaluation) {
                $scoreMoyen += $evaluation->getScoreTest() ?? 0;
                $noteMoyenne += $evaluation->getNoteEntretien() ?? 0;
                $evalStats[0] += $evaluation->getCompetencesTechniques() ?? 0;
                $evalStats[1] += $evaluation->getCompetencesComportementales() ?? 0;
                $evalStats[2] += $evaluation->getCommunication() ?? 0;
                $evalStats[3] += $evaluation->getMotivation() ?? 0;
                $evalStats[4] += $evaluation->getExperience() ?? 0;
            }

            $scoreMoyen = round($scoreMoyen / $totalEvals, 1);
            $noteMoyenne = round($noteMoyenne / $totalEvals, 1);
            $evalStats = array_map(static fn (float|int $value): float => round($value / $totalEvals, 1), $evalStats);
            $derniereDecision = end($evaluations)?->getDecision();
        }

        return $this->render('front/dashboard.html.twig', [
            'totalEntretiens' => $totalEntretiens,
            'termines' => $termines,
            'planifies' => $planifies,
            'totalEvals' => $totalEvals,
            'dernierEntretiens' => $dernierEntretiens,
            'scoreMoyen' => $scoreMoyen,
            'noteMoyenne' => round($noteMoyenne),
            'evalStats' => $evalStats,
            'derniereDecision' => $derniereDecision,
            'userName' => (string) $session->get('user_name', 'Client'),
        ]);
    }

    #[Route('/entretiens', name: 'app_front_entretiens', methods: ['GET'])]
    public function entretiens(Request $request, EntretienRepository $repo, SessionInterface $session): Response
    {
        $userId = $this->requireClient($session);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $search = (string) $request->query->get('search', '');
        $type = (string) $request->query->get('type', '');
        $statut = (string) $request->query->get('statut', '');

        return $this->render('front/entretiens.html.twig', [
            'entretiens' => $repo->findForUser($userId, $search ?: null, $type ?: null, $statut ?: null, 'e.dateEntretien', 'DESC'),
            'search' => $search,
            'type' => $type,
            'statut' => $statut,
            'userName' => (string) $session->get('user_name', 'Client'),
        ]);
    }

    #[Route('/entretiens/{id}', name: 'app_front_entretien_show', methods: ['GET'])]
    public function entretienShow(int $id, EntretienRepository $repo, SessionInterface $session): Response
    {
        $userId = $this->requireClient($session);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $entretien = $repo->createQueryBuilder('e')
            ->andWhere('e.id = :id')
            ->leftJoin(Candidature::class, 'c', 'WITH', 'c.id_candidature = e.idCandidature')
            ->andWhere('(e.idUtilisateur = :userId OR c.id_utilisateur = :userId)')
            ->setParameter('id', $id)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$entretien) {
            throw $this->createNotFoundException('Entretien introuvable.');
        }

        return $this->render('front/entretien_show.html.twig', [
            'entretien' => $entretien,
            'userName' => (string) $session->get('user_name', 'Client'),
        ]);
    }

    #[Route('/calendrier', name: 'app_front_calendrier', methods: ['GET'])]
    public function calendrier(SessionInterface $session): Response
    {
        $userId = $this->requireClient($session);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        return $this->render('front/calendrier.html.twig', [
            'userName'  => (string) $session->get('user_name', 'Client'),
            'dataUrl'   => $this->generateUrl('app_front_calendrier_data'),
        ]);
    }

    #[Route('/calendrier/data', name: 'app_front_calendrier_data', methods: ['GET'])]
    public function calendrierData(EntretienRepository $repo, SessionInterface $session): JsonResponse
    {
        $userId = $this->requireClient($session);
        if ($userId instanceof RedirectResponse) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        $events = [];
        foreach ($repo->findForUser($userId) as $entretien) {
            $date = $entretien->getDateEntretien();
            if ($date === null) {
                continue;
            }

            $type   = $entretien->getTypeEntretien() ?? 'Entretien';
            $lieu   = $entretien->getLieu() ?? '';
            $statut = $entretien->getStatutEntretien() ?? '';
            $time   = $entretien->getHeureEntretien();

            if ($time !== null) {
                $start = $date->format('Y-m-d') . 'T' . $time->format('H:i:s');
                $endDt = new \DateTime($date->format('Y-m-d') . ' ' . $time->format('H:i:s'));
                $end   = $endDt->modify('+1 hour')->format('Y-m-d\TH:i:s');
            } else {
                $start = $date->format('Y-m-d');
                $end   = null;
            }

            $events[] = [
                'id'    => $entretien->getId(),
                'title' => 'Entretien ' . $type . ($lieu !== '' ? ' — ' . $lieu : ''),
                'start' => $start,
                'end'   => $end,
                'color' => $type === 'TECHNIQUE' ? '#7209b7' : '#4361ee',
                'url'   => $this->generateUrl('app_front_entretien_show', ['id' => $entretien->getId()]),
                'extendedProps' => [
                    'type'   => $type,
                    'statut' => $statut,
                    'lieu'   => $lieu,
                ],
            ];
        }

        return new JsonResponse($events);
    }

    #[Route('/evaluations', name: 'app_front_evaluations', methods: ['GET'])]
    public function evaluations(EvaluationEntretienRepository $repo, SessionInterface $session): Response
    {
        $userId = $this->requireClient($session);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        return $this->render('front/evaluations.html.twig', [
            'evaluations' => $repo->findForUser($userId),
            'userName' => (string) $session->get('user_name', 'Client'),
        ]);
    }

    #[Route('/contrats/{id}/pdf', name: 'app_front_contrat_pdf', methods: ['GET'])]
    public function contratPdf(int $id, EntityManagerInterface $em, SessionInterface $session, Environment $twig): Response
    {
        $userId = $this->requireClient($session);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $contrat = $em->getRepository(ContratEmbauche::class)->find($id);
        if (!$contrat) {
            throw $this->createNotFoundException('Contrat introuvable.');
        }

        // Verify ownership through the chain: contrat → recrutement → entretien → candidature → utilisateur
        $row = $em->getConnection()->executeQuery(
            "SELECT ca.id_utilisateur, e.type_entretien, e.date_entretien, e.lieu,
                    r.date_decision, r.decision_finale, u.nom, u.prenom, u.email
             FROM contrat_embauche c
             INNER JOIN recrutement r  ON r.id_recrutement = c.id_recrutement
             INNER JOIN entretien e    ON e.id_entretien   = r.id_entretien
             INNER JOIN candidature ca ON ca.id_candidature = e.id_candidature
             LEFT  JOIN utilisateur u   ON u.id_utilisateur = ca.id_utilisateur
             WHERE c.id_contrat = :id AND ca.id_utilisateur = :userId",
            ['id' => $id, 'userId' => $userId]
        )->fetchAssociative();

        if (!$row) {
            throw $this->createAccessDeniedException('Accès refusé à ce contrat.');
        }

        $logoPath   = $this->getParameter('kernel.project_dir') . '/public/images/logo.png';
        $logoBase64 = is_file($logoPath)
            ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath))
            : null;

        $html = $twig->render('pdf/contrat_report.html.twig', [
            'contrat'      => $contrat,
            'meta'         => $row,
            'userName'     => (string) $session->get('user_name', 'Candidat'),
            'logoBase64'   => $logoBase64,
            'generatedAt'  => new \DateTime(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('contrat-%d-%s.pdf', $id, (new \DateTime())->format('Ymd'));

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    private function requireClient(SessionInterface $session): int|RedirectResponse
    {
        $userId = (int) $session->get('user_id', 0);
        $userRole = (string) $session->get('user_role', '');

        if ($userId <= 0 || $userRole !== 'CLIENT') {
            $this->addFlash('error', 'Veuillez vous connecter en client.');
            return $this->redirectToRoute('app_signin');
        }

        return $userId;
    }
}

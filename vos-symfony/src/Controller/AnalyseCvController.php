<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\AnalyseCv;
use App\Repository\AnalyseCvRepository;
use App\Repository\CandidatureRepository;
use App\Service\CVAnalysisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Psr\Log\LoggerInterface;

#[Route('/analyse-cv', name: 'analyse_cv_')]
class AnalyseCvController extends AbstractController
{
    public function __construct(
        private CVAnalysisService $cvAnalysisService,
        private CandidatureRepository $candidatureRepository,
        private AnalyseCvRepository $analyseCvRepository,
        private KernelInterface $kernel,
        private LoggerInterface $logger
    ) {}

    /**
     * Analyser le CV d'une candidature
     */
    #[Route('/analyser/{idCandidature}', name: 'analyser', methods: ['POST'])]
    public function analyser(int $idCandidature): JsonResponse
    {
        try {
            // Récupérer la candidature
            $candidature = $this->candidatureRepository->find($idCandidature);
            if (!$candidature) {
                return $this->json([
                    'success' => false,
                    'error' => 'Candidature non trouvée'
                ], Response::HTTP_NOT_FOUND);
            }

            // Vérifier que le CV existe
            $cvPath = $candidature->getCv();
            if (!$cvPath) {
                return $this->json([
                    'success' => false,
                    'error' => 'Aucun CV associé à cette candidature'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Extraire le texte du CV
            $cvText = $this->extractCVText($cvPath);
            if (empty($cvText)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Impossible de lire le contenu du CV'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Analyser le CV avec le service
            $analyseCv = $this->cvAnalysisService->analyzerCV($cvText, $candidature);

            return $this->json([
                'success' => true,
                'message' => 'Analyse du CV complétée',
                'analyse_id' => $analyseCv->getIdAnalyse(),
                'score' => $analyseCv->getScoreCv(),
                'date_analyse' => $analyseCv->getDateAnalyse()?->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error('CV Analysis Error', [
                'error' => $e->getMessage(),
                'candidature_id' => $idCandidature
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de l\'analyse: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Afficher les résultats de l'analyse d'un CV
     */
    #[Route('/afficher/{idCandidature}', name: 'afficher', methods: ['GET'])]
    public function afficher(int $idCandidature): Response
    {
        try {
            // Récupérer la candidature
            $candidature = $this->candidatureRepository->find($idCandidature);
            if (!$candidature) {
                throw $this->createNotFoundException('Candidature non trouvée');
            }

            // Récupérer la dernière analyse
            $analyseCv = $this->analyseCvRepository->findLatestByCandidature($idCandidature);
            if (!$analyseCv) {
                return $this->render('analyse_cv/no_analysis.html.twig', [
                    'candidature' => $candidature
                ]);
            }

            // Récupérer toutes les analyses pour l'historique
            $historique = $this->analyseCvRepository->findByCandidature($idCandidature);

            return $this->render('analyse_cv/afficher.html.twig', [
                'candidature' => $candidature,
                'analyse' => $analyseCv,
                'historique' => $historique
            ]);

        } catch (\Exception $e) {
            $this->logger->error('CV Analysis Display Error', [
                'error' => $e->getMessage(),
                'candidature_id' => $idCandidature
            ]);

            throw $this->createNotFoundException('Analyse non disponible');
        }
    }

    /**
     * Récupérer les analyses en JSON (pour les appels API)
     */
    #[Route('/json/{idCandidature}', name: 'json', methods: ['GET'])]
    public function getAnalysisJson(int $idCandidature): JsonResponse
    {
        try {
            // Récupérer la candidature
            $candidature = $this->candidatureRepository->find($idCandidature);
            if (!$candidature) {
                return $this->json([
                    'success' => false,
                    'error' => 'Candidature non trouvée'
                ], Response::HTTP_NOT_FOUND);
            }

            // Récupérer la dernière analyse
            $analyseCv = $this->analyseCvRepository->findLatestByCandidature($idCandidature);
            if (!$analyseCv) {
                return $this->json([
                    'success' => false,
                    'error' => 'Aucune analyse disponible'
                ], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'success' => true,
                'analyse' => [
                    'id' => $analyseCv->getIdAnalyse(),
                    'competences_detectees' => $analyseCv->getCompetencesDetectees() ?? [],
                    'points_forts' => $analyseCv->getPointsForts() ?? [],
                    'points_faibles' => $analyseCv->getPointsFaibles() ?? [],
                    'score_cv' => $analyseCv->getScoreCv(),
                    'suggestions' => $analyseCv->getSuggestions() ?? [],
                    'date_analyse' => $analyseCv->getDateAnalyse()?->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Extraire le texte du CV (PDF ou texte)
     */
    private function extractCVText(string $cvPath): string
    {
        try {
            // Construire le chemin complet
            $projectDir = $this->kernel->getProjectDir();
            // APRÈS (corrigé)
        $fullPath = $projectDir . '/public/' . $cvPath;

            // Vérifier que le fichier existe
            if (!file_exists($fullPath)) {
                $this->logger->warning('CV file not found', ['path' => $fullPath]);
                return '';
            }

            // Déterminer le type de fichier
            $fileExtension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

            if ($fileExtension === 'pdf') {
                // Extraire le texte du PDF
                return $this->extractTextFromPDF($fullPath);
            } elseif ($fileExtension === 'txt') {
                // Lire le fichier texte
                return file_get_contents($fullPath) ?: '';
            } else {
                // Essayer de lire comme texte par défaut
                return file_get_contents($fullPath) ?: '';
            }

        } catch (\Exception $e) {
            $this->logger->error('Error extracting CV text', [
                'error' => $e->getMessage(),
                'cv_path' => $cvPath
            ]);
            return '';
        }
    }

    /**
     * Extraire le texte d'un PDF
     * Note: Pour une solution robuste, utilisez la bibliothèque smalot/pdfparser
     */
    private function extractTextFromPDF(string $pdfPath): string
    {
        try {
            // Vérifier si pdfparser est installé
            if (class_exists('Smalot\PdfParser\Parser')) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($pdfPath);
                return $pdf->getText() ?: 'Impossible d\'extraire le texte du PDF';
            }

            // Fallback: essayer de lire le fichier brut et extraire du texte
            // Cette méthode est très basique et peut ne pas fonctionner pour tous les PDFs
            $content = file_get_contents($pdfPath);
            if (!$content) {
                return '';
            }

            // Extraire du texte brut du PDF (très basique)
            // Supprimer les caractères non-imprimables et extraire du texte
            $text = '';
            $parts = preg_split('/\x00/', $content);

            foreach ($parts as $part) {
                // Extraire les chaînes de caractères imprimables
                if (preg_match_all('/[ -~]{3,}/', $part, $matches)) {
                    $text .= implode(' ', $matches[0]) . ' ';
                }
            }

            return trim($text) ?: 'Impossible d\'extraire le texte du PDF';

        } catch (\Exception $e) {
            $this->logger->error('PDF Extraction Error', [
                'error' => $e->getMessage(),
                'pdf_path' => $pdfPath
            ]);

            // Retourner un texte par défaut pour éviter une analyse vide
            return 'CV (contenu non accessible directement)';
        }
    }
}

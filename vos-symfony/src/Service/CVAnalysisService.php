<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\AnalyseCv;
use App\Entity\Candidature;
use Psr\Log\LoggerInterface;

class CVAnalysisService
{
    private HttpClientInterface $httpClient;
    private EntityManagerInterface $entityManager;
    private string $groqApiKey;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
        string $groqApiKey,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->groqApiKey = $groqApiKey;
        $this->logger = $logger;
    }

    /**
     * Analyse un CV via Groq API et retourne une entité AnalyseCv
     */
    public function analyzerCV(string $cvText, Candidature $candidature): AnalyseCv
    {
        try {
            // Valider la clé API
            if (empty($this->groqApiKey)) {
                throw new \Exception('Clé API Groq non configurée');
            }

            // Nettoyer et valider le texte du CV
            $cvText = trim($cvText);
            // Nettoyer les caractères UTF-8 invalides
            $cvText = mb_convert_encoding($cvText, 'UTF-8', 'UTF-8');
            $cvText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cvText);
            $cvText = iconv('UTF-8', 'UTF-8//IGNORE', $cvText);
            if (empty($cvText)) {
                throw new \Exception('Le texte du CV ne peut pas être vide');
            }

            // Limiter la longueur du CV (max 8000 caractères pour l'API)
            if (strlen($cvText) > 8000) {
                $cvText = substr($cvText, 0, 8000);
            }

            // Préparer le prompt pour Groq
            $prompt = $this->buildAnalysisPrompt($cvText, $candidature);

            // Préparer la requête à Groq
            $requestPayload = [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un expert en ressources humaines et recrutement. Analyse les CVs de manière professionnelle et objective. Réponds UNIQUEMENT en JSON valide, sans texte supplémentaire.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 1024,
                'response_format' => ['type' => 'json_object']
            ];

            $this->logger->info('CV Analysis Request', [
                'candidature_id' => $candidature->getIdCandidature(),
                'cv_text_length' => strlen($cvText),
                'api_key_valid' => !empty($this->groqApiKey)
            ]);

            // Appel API Groq
            $response = $this->httpClient->request(
                'POST',
                'https://api.groq.com/openai/v1/chat/completions',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->groqApiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $requestPayload,
                    'timeout' => 30,
                    'verify_peer' => true,
                    'verify_host' => true,
                ]
            );

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $responseContent = $response->getContent(false);
                $this->logger->error('CV Analysis API Error', [
                    'status_code' => $statusCode,
                    'response_preview' => substr($responseContent, 0, 500)
                ]);
                throw new \Exception('Erreur API Groq (HTTP ' . $statusCode . ')');
            }

            $data = $response->toArray();

            if (!isset($data['choices'][0]['message']['content'])) {
                throw new \Exception('Réponse invalide de Groq API');
            }

            $analysisJson = $data['choices'][0]['message']['content'];
            $this->logger->info('CV Analysis Response', [
                'response_length' => strlen($analysisJson)
            ]);

            // Parser la réponse JSON
            $analysis = $this->parseAnalysisResponse($analysisJson);

            // Créer l'entité AnalyseCv
            $analyseCv = new AnalyseCv();
            $analyseCv->setCandidature($candidature);
            $analyseCv->setIdCandidature($candidature->getIdCandidature());
            $analyseCv->setCompetencesDetectees($analysis['competences_detectees'] ?? []);
            $analyseCv->setPointsForts($analysis['points_forts'] ?? []);
            $analyseCv->setPointsFaibles($analysis['points_faibles'] ?? []);
            $analyseCv->setScoreCv($analysis['score_cv'] ?? 0);
            $analyseCv->setSuggestions($analysis['suggestions'] ?? []);
            $analyseCv->setDateAnalyse(new \DateTime());

            // Sauvegarder en base de données
            $this->entityManager->persist($analyseCv);
            $this->entityManager->flush();

            $this->logger->info('CV Analysis Saved', [
                'analyse_id' => $analyseCv->getIdAnalyse(),
                'score' => $analyseCv->getScoreCv()
            ]);

            return $analyseCv;

        } catch (\Exception $e) {
            $this->logger->error('CV Analysis Service Exception', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Construit le prompt pour l'analyse du CV
     */
    private function buildAnalysisPrompt(string $cvText, Candidature $candidature): string
    {
        $context = "Contexte du candidat:\n";
        $context .= "- Domaine d'expérience: " . ($candidature->getDomaineExperience() ?? 'Non spécifié') . "\n";
        $context .= "- Niveau d'expérience: " . ($candidature->getNiveauExperience() ?? 'Non spécifié') . "\n";
        $context .= "- Dernier poste: " . ($candidature->getDernierPoste() ?? 'Non spécifié') . "\n";
        $context .= "- Années d'expérience: " . ($candidature->getAnneesExperience() ?? '0') . "\n\n";

        $prompt = "Analyse le CV suivant de manière professionnelle et objective:\n\n";
        $prompt .= "=== CV ===\n";
        $prompt .= $cvText . "\n";
        $prompt .= "=== FIN CV ===\n\n";
        $prompt .= $context;
        $prompt .= "\nFournis une analyse complète en JSON avec la structure suivante (OBLIGATOIRE):\n";
        $prompt .= "{\n";
        $prompt .= '  "competences_detectees": ["compétence1", "compétence2", ...] (liste des compétences trouvées),' . "\n";
        $prompt .= '  "points_forts": ["force1", "force2", ...] (liste des points forts du CV),' . "\n";
        $prompt .= '  "points_faibles": ["faiblesse1", "faiblesse2", ...] (domaines à améliorer),' . "\n";
        $prompt .= '  "score_cv": <nombre 0-100> (évaluation globale du CV),' . "\n";
        $prompt .= '  "suggestions": ["suggestion1", "suggestion2", ...] (recommandations pour améliorer le CV)' . "\n";
        $prompt .= "}\n\n";
        $prompt .= "Réponds UNIQUEMENT avec le JSON, sans texte supplémentaire.";

        return $prompt;
    }

    /**
     * Parse la réponse JSON de Groq
     */
    private function parseAnalysisResponse(string $jsonResponse): array
    {
        try {
            // Essayer de décoder directement
            $data = json_decode($jsonResponse, true);

            if ($data === null) {
                // Essayer de extraire le JSON du texte
                if (preg_match('/\{[\s\S]*\}/', $jsonResponse, $matches)) {
                    $data = json_decode($matches[0], true);
                }
            }

            if ($data === null) {
                throw new \Exception('Invalid JSON response from Groq');
            }

            // Valider et nettoyer les données
            return [
                'competences_detectees' => $this->ensureArray($data['competences_detectees'] ?? []),
                'points_forts' => $this->ensureArray($data['points_forts'] ?? []),
                'points_faibles' => $this->ensureArray($data['points_faibles'] ?? []),
                'score_cv' => $this->ensureInt($data['score_cv'] ?? 0),
                'suggestions' => $this->ensureArray($data['suggestions'] ?? [])
            ];

        } catch (\Exception $e) {
            $this->logger->error('JSON Parse Error', [
                'error' => $e->getMessage(),
                'response_preview' => substr($jsonResponse, 0, 200)
            ]);

            // Retourner une structure par défaut
            return [
                'competences_detectees' => [],
                'points_forts' => [],
                'points_faibles' => [],
                'score_cv' => 0,
                'suggestions' => ['Impossible d\'analyser le CV pour le moment']
            ];
        }
    }

    /**
     * Assure que la valeur est un tableau
     */
    private function ensureArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            return [$value];
        }
        return [];
    }

    /**
     * Assure que la valeur est un entier entre 0 et 100
     */
    private function ensureInt($value): int
    {
        $int = (int) $value;
        return max(0, min(100, $int));
    }
}

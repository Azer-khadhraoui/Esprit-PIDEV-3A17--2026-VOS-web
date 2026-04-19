<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\OffreEmploi;
use App\Entity\Candidature;
use App\Entity\Entretien;
use App\Entity\PreferenceCandidature;
use App\Entity\User;
use Psr\Log\LoggerInterface;

class ChatbotService
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
     * Obtient le contexte de l'application pour augmenter les réponses du chatbot
     */
    private function getApplicationContext(?User $user = null): string
    {
        try {
            $context = "INSTRUCTIONS CRITIQUES:\n";
            $context .= "1. Tu es un assistant pour la plateforme de recrutement VOS.\n";
            $context .= "2. Aide les utilisateurs avec les offres d'emploi, candidatures et préférences.\n";
            $context .= "3. Tu peux répondre à des questions générales sur les métiers en IT.\n";
            $context .= "4. Sois courtois, utile et professionnel.\n";
            $context .= "5. Réponds en français, de façon claire et directe.\n";
            $context .= "6. Garde tes réponses courtes (max 3-4 lignes sauf si demandé).\n";
            $context .= "7. Si tu ne sais pas, propose d'aider autrement.\n\n";
            
            // Offres disponibles
            $offresRepository = $this->entityManager->getRepository(OffreEmploi::class);
            $offres = $offresRepository->findBy(['statutOffre' => 'OUVERTE']);
            
            if (count($offres) > 0) {
                $context .= "===== OFFRES D'EMPLOI ACTUELLES =====\n";
                foreach ($offres as $offre) {
                    $titre = $offre->getTitre() ?? 'Sans titre';
                    $lieu = $offre->getLieu() ?? 'Non spécifié';
                    $type = $offre->getTypeContrat() ?? 'Type non spécifié';
                    $context .= "[$titre] $type à $lieu\n";
                }
                $context .= "===== FIN DES OFFRES =====\n\n";
            } else {
                $context .= "Aucune offre d'emploi disponible actuellement.\n\n";
            }
            
            // Données de l'utilisateur connecté
            if ($user) {
                $userId = $user->getId();
                
                // Candidatures de l'utilisateur
                $candidatureRepository = $this->entityManager->getRepository(Candidature::class);
                $candidatures = $candidatureRepository->findBy(['id_utilisateur' => $userId]);
                
                if (count($candidatures) > 0) {
                    $context .= "\nCANDIDATURES DE L'UTILISATEUR:\n";
                    foreach ($candidatures as $cand) {
                        $status = $cand->getStatut() ?? 'Inconnu';
                        $offreId = $cand->getIdOffre();
                        $context .= "- Offre #$offreId: $status\n";
                    }
                } else {
                    $context .= "\nL'utilisateur n'a pas encore de candidatures.\n";
                }
                
                // Préférences de l'utilisateur
                $prefRepository = $this->entityManager->getRepository(PreferenceCandidature::class);
                $prefs = $prefRepository->findBy(['id_utilisateur' => $userId], [], 1);
                
                if (count($prefs) > 0) {
                    $pref = $prefs[0];
                    $context .= "\nPREFERENCES DE L'UTILISATEUR:\n";
                    if ($pref->getTypePosteSouhaite()) {
                        $context .= "- Poste: " . $pref->getTypePosteSouhaite() . "\n";
                    }
                    if ($pref->getModeTravail()) {
                        $context .= "- Mode de travail: " . $pref->getModeTravail() . "\n";
                    }
                    if ($pref->getDisponibilite()) {
                        $context .= "- Disponibilité: " . $pref->getDisponibilite() . "\n";
                    }
                    if ($pref->getTypeContratSouhaite()) {
                        $context .= "- Type de contrat: " . $pref->getTypeContratSouhaite() . "\n";
                    }
                }
            }
            
            return $context;
        } catch (\Exception $e) {
            $this->logger->warning('Error building context: ' . $e->getMessage());
            return "Tu es un assistant pour une plateforme de recrutement VOS. Aide les utilisateurs avec leurs offres d'emploi et candidatures.";
        }
    }

    /**
     * Envoie un message au chatbot Groq et reçoit une réponse
     */
    public function chat(string $message, array $conversationHistory = [], ?User $user = null): array
    {
        try {
            // Valider la clé API
            if (empty($this->groqApiKey)) {
                $this->logger->error('Groq API Key is missing');
                return [
                    'success' => false,
                    'error' => 'Clé API Groq non configurée. Vérifiez le fichier .env.local'
                ];
            }

            // Valider que c'est bien une clé Groq
            if (strlen($this->groqApiKey) < 20) {
                $this->logger->error('Groq API Key format invalid: too short');
                return [
                    'success' => false,
                    'error' => 'Clé API Groq invalide (trop courte)'
                ];
            }

            // Nettoyer les inputs
            $message = trim($message);
            if (empty($message)) {
                return [
                    'success' => false,
                    'error' => 'Le message ne peut pas être vide'
                ];
            }

            // Déterminer l'intention et retourner une réponse directe si applicable
            $intentResponse = $this->detectIntentAndRespond($message, $user);
            if ($intentResponse !== null) {
                return $intentResponse;
            }

            // Préparer le contexte
            $applicationContext = $this->getApplicationContext($user);
            
            // Préparer les messages - limiter l'historique à 4 messages
            $messages = [
                [
                    'role' => 'system',
                    'content' => $applicationContext
                ]
            ];

            // Ajouter l'historique de conversation (limité à 4 derniers messages)
            $historyLimit = array_slice($conversationHistory, -4);
            foreach ($historyLimit as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $messages[] = [
                        'role' => $msg['role'],
                        'content' => trim((string)$msg['content'])
                    ];
                }
            }

            // Ajouter le nouveau message avec contexte renforcé
            $userMessage = $message . "\n\n[CONTEXTE: Réponds UNIQUEMENT avec les informations du système. Ne crée pas de données.]";
            $messages[] = [
                'role' => 'user',
                'content' => $userMessage
            ];

            // Préparer la requête
            $requestPayload = [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => $messages,
                'temperature' => 0.3,
                'max_tokens' => 256,
            ];

            $this->logger->info('Groq API Request', [
                'model' => 'llama-3.3-70b-versatile',
                'messages_count' => count($messages),
                'message_length' => strlen($message),
                'api_key_valid' => !empty($this->groqApiKey)
            ]);

            // Appel API Groq
            $response = $this->httpClient->request('POST', 
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
            $this->logger->info('Groq API Response Status', ['status_code' => $statusCode]);

            if ($statusCode !== 200) {
                $responseContent = $response->getContent(false);
                
                $this->logger->error('Groq API Error Response', [
                    'status_code' => $statusCode,
                    'response_preview' => substr($responseContent, 0, 500)
                ]);
                
                // Message d'erreur plus spécifique
                $errorMessage = 'Erreur API Groq (HTTP ' . $statusCode . ')';
                if ($statusCode === 401) {
                    $errorMessage = 'Clé API invalide ou expirée';
                } elseif ($statusCode === 400) {
                    $errorMessage = 'Requête invalide - vérifiez votre clé API';
                }
                
                return [
                    'success' => false,
                    'error' => $errorMessage
                ];
            }

            $data = $response->toArray();

            if (isset($data['choices'][0]['message']['content'])) {
                $responseContent = $data['choices'][0]['message']['content'];
                $this->logger->info('Groq API Success', [
                    'response_length' => strlen($responseContent)
                ]);
                
                return [
                    'success' => true,
                    'response' => $responseContent,
                    'message' => $message
                ];
            }

            $this->logger->error('Groq API Response missing content', [
                'response_structure' => array_keys($data)
            ]);
            
            return [
                'success' => false,
                'error' => 'Réponse invalide de Groq API'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Chatbot Service Exception', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'success' => false,
                'error' => 'Erreur serveur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Détecte l'intention de l'utilisateur et retourne une réponse directe si c'est une demande spécifique
     */
    private function detectIntentAndRespond(string $message, ?User $user = null): ?array
    {
        $messageLower = strtolower(trim($message));
        
        // Intention 1: L'utilisateur demande les offres disponibles
        if (
            strpos($messageLower, 'offre') !== false && 
            (strpos($messageLower, 'disponible') !== false || 
             strpos($messageLower, 'quelles') !== false ||
             strpos($messageLower, 'liste') !== false ||
             strpos($messageLower, 'cherche') !== false)
        ) {
            return $this->respondWithOffers();
        }
        
        // Intention 2: L'utilisateur demande ses candidatures
        if (
            strpos($messageLower, 'candidature') !== false &&
            (strpos($messageLower, 'mes') !== false || 
             strpos($messageLower, 'quelles') !== false ||
             strpos($messageLower, 'liste') !== false)
        ) {
            return $this->respondWithCandidatures($user);
        }
        
        // Intention 3: L'utilisateur demande ses préférences
        if (
            strpos($messageLower, 'préférence') !== false ||
            strpos($messageLower, 'preference') !== false
        ) {
            return $this->respondWithPreferences($user);
        }
        
        // Intention 4: L'utilisateur demande comment postuler
        if (
            (strpos($messageLower, 'comment') !== false && strpos($messageLower, 'appliqu') !== false) ||
            strpos($messageLower, 'comment postuler') !== false ||
            strpos($messageLower, 'comment candidater') !== false ||
            (strpos($messageLower, 'poser') !== false && strpos($messageLower, 'candidature') !== false) ||
            strpos($messageLower, 'procédure candidature') !== false
        ) {
            return $this->respondWithApplicationProcess();
        }
        
        // Intention 5: L'utilisateur demande une définition ou explication sur un métier
        if (
            strpos($messageLower, 'qu\'est') !== false ||
            strpos($messageLower, 'quest') !== false ||
            strpos($messageLower, 'c\'est quoi') !== false ||
            strpos($messageLower, 'définition') !== false ||
            strpos($messageLower, 'definition') !== false ||
            strpos($messageLower, 'expliquez') !== false ||
            strpos($messageLower, 'what is') !== false ||
            (strpos($messageLower, 'qui') !== false && (strpos($messageLower, 'développeur') !== false || strpos($messageLower, 'developer') !== false))
        ) {
            return $this->respondWithDefinition($message);
        }
        
        // Intention 6: L'utilisateur demande comment consulter/voir les offres
        if (
            (strpos($messageLower, 'consulter') !== false && strpos($messageLower, 'offre') !== false) ||
            (strpos($messageLower, 'voir') !== false && strpos($messageLower, 'offre') !== false) ||
            strpos($messageLower, 'comment trouver offre') !== false ||
            strpos($messageLower, 'comment voir les offres') !== false
        ) {
            return $this->respondWithHowToViewOffers();
        }
        
        // Intention 7: L'utilisateur demande comment modifier son profil
        if (
            (strpos($messageLower, 'modifier') !== false && strpos($messageLower, 'profil') !== false) ||
            (strpos($messageLower, 'mettre à jour') !== false && strpos($messageLower, 'profil') !== false) ||
            (strpos($messageLower, 'editer') !== false && strpos($messageLower, 'profil') !== false) ||
            (strpos($messageLower, 'change') !== false && strpos($messageLower, 'profil') !== false) ||
            strpos($messageLower, 'editer mon compte') !== false ||
            strpos($messageLower, 'modifier mes infos') !== false
        ) {
            return $this->respondWithProfileManagement();
        }
        
        // Intention 8: L'utilisateur demande comment suivre sa candidature
        if (
            (strpos($messageLower, 'suivre') !== false && strpos($messageLower, 'candidature') !== false) ||
            (strpos($messageLower, 'statut') !== false && strpos($messageLower, 'candidature') !== false) ||
            (strpos($messageLower, 'etat') !== false && strpos($messageLower, 'candidature') !== false) ||
            strpos($messageLower, 'où en est ma candidature') !== false ||
            strpos($messageLower, 'comment savoir si accepté') !== false
        ) {
            return $this->respondWithApplicationTracking($user);
        }
        
        // Pas d'intention spécifique détectée - utiliser le modèle Groq
        return null;
    }

    /**
     * Retourne la liste des offres disponibles
     */
    private function respondWithOffers(): array
    {
        $offresRepository = $this->entityManager->getRepository(OffreEmploi::class);
        $offres = $offresRepository->findBy(['statutOffre' => 'OUVERTE']);
        
        if (empty($offres)) {
            return [
                'success' => true,
                'response' => 'Actuellement, aucune offre d\'emploi n\'est disponible sur la plateforme.',
                'message' => '',
                'fromServer' => true
            ];
        }
        
        $response = "📋 OFFRES D'EMPLOI DISPONIBLES (" . count($offres) . " total):\n\n";
        foreach ($offres as $offre) {
            $titre = $offre->getTitre() ?? 'Sans titre';
            $type = $offre->getTypeContrat() ?? 'Type non spécifié';
            $lieu = $offre->getLieu() ?? 'Non spécifié';
            $response .= "✓ $titre - $type - $lieu\n";
        }
        $response .= "\nVoulez-vous plus d'informations sur une offre spécifique?";
        
        return [
            'success' => true,
            'response' => $response,
            'message' => '',
            'fromServer' => true
        ];
    }

    /**
     * Retourne les candidatures de l'utilisateur
     */
    private function respondWithCandidatures(?User $user): array
    {
        if (!$user) {
            return [
                'success' => true,
                'response' => 'Veuillez vous connecter pour voir vos candidatures.',
                'message' => '',
                'fromServer' => true
            ];
        }
        
        $candidatureRepository = $this->entityManager->getRepository(Candidature::class);
        $candidatures = $candidatureRepository->findBy(['id_utilisateur' => $user->getId()]);
        
        if (empty($candidatures)) {
            return [
                'success' => true,
                'response' => 'Vous n\'avez pas encore de candidatures.',
                'message' => '',
                'fromServer' => true
            ];
        }
        
        $response = "📨 VOS CANDIDATURES (" . count($candidatures) . " total):\n\n";
        foreach ($candidatures as $cand) {
            $offre = $this->entityManager->find(OffreEmploi::class, $cand->getIdOffre());
            $titrOffre = $offre ? $offre->getTitre() : 'Offre #' . $cand->getIdOffre();
            $status = $cand->getStatut() ?? 'Inconnu';
            $icon = $status === 'Accepté' ? '✅' : ($status === 'Refusé' ? '❌' : '⏳');
            $response .= "$icon $titrOffre - $status\n";
        }
        
        return [
            'success' => true,
            'response' => $response,
            'message' => '',
            'fromServer' => true
        ];
    }

    /**
     * Retourne les préférences de l'utilisateur
     */
    private function respondWithPreferences(?User $user): array
    {
        if (!$user) {
            return [
                'success' => true,
                'response' => 'Veuillez vous connecter pour voir vos préférences.',
                'message' => '',
                'fromServer' => true
            ];
        }
        
        $prefRepository = $this->entityManager->getRepository(PreferenceCandidature::class);
        $prefs = $prefRepository->findBy(['id_utilisateur' => $user->getId()], [], 1);
        
        if (empty($prefs)) {
            return [
                'success' => true,
                'response' => 'Vous n\'avez pas encore configuré vos préférences.',
                'message' => '',
                'fromServer' => true
            ];
        }
        
        $pref = $prefs[0];
        $response = "⚙️ VOS PRÉFÉRENCES DE RECHERCHE:\n\n";
        
        if ($pref->getTypePosteSouhaite()) {
            $response .= "📌 Poste: " . $pref->getTypePosteSouhaite() . "\n";
        }
        if ($pref->getModeTravail()) {
            $response .= "🏢 Mode de travail: " . $pref->getModeTravail() . "\n";
        }
        if ($pref->getDisponibilite()) {
            $response .= "📅 Disponibilité: " . $pref->getDisponibilite() . "\n";
        }
        if ($pref->getTypeContratSouhaite()) {
            $response .= "📝 Type de contrat: " . $pref->getTypeContratSouhaite() . "\n";
        }
        if ($pref->getPretentionSalariale()) {
            $response .= "💰 Prétention salariale: " . $pref->getPretentionSalariale() . " TND\n";
        }
        
        return [
            'success' => true,
            'response' => $response,
            'message' => '',
            'fromServer' => true
        ];
    }

    /**
     * Explique le processus de candidature
     */
    private function respondWithApplicationProcess(): array
    {
        $response = "📋 COMMENT POSTULER POUR UNE OFFRE:\n\n";
        $response .= "1️⃣ Consultez les offres disponibles en écrivant: 'Quelles offres sont disponibles?'\n\n";
        $response .= "2️⃣ Cliquez sur une offre qui vous intéresse\n\n";
        $response .= "3️⃣ Lisez les détails: compétences requises, localisation, type de contrat\n\n";
        $response .= "4️⃣ Cliquez sur 'Postuler' ou 'Candidater'\n\n";
        $response .= "5️⃣ Remplissez votre profil avec vos informations\n\n";
        $response .= "6️⃣ Répondez aux questions spécifiques de l'offre\n\n";
        $response .= "7️⃣ Validez et envoyez votre candidature\n\n";
        $response .= "📧 Vous recevrez une confirmation par email et pourrez suivre le statut de votre candidature dans votre compte.";
        
        return [
            'success' => true,
            'response' => $response,
            'message' => '',
            'fromServer' => true
        ];
    }

    /**
     * Explique comment consulter les offres disponibles
     */
    private function respondWithHowToViewOffers(): array
    {
        $offresRepository = $this->entityManager->getRepository(OffreEmploi::class);
        $offres = $offresRepository->findBy(['statutOffre' => 'OUVERTE']);
        
        $response = "📌 COMMENT CONSULTER LES OFFRES:\n\n";
        $response .= "MÉTHODE 1️⃣ - VIA LE CHATBOT:\n";
        $response .= "Écrivez simplement: 'Quelles offres sont disponibles?' ou 'Montre-moi les offres'\n\n";
        
        $response .= "MÉTHODE 2️⃣ - VIA LE SITE:\n";
        $response .= "1. Allez à la section 'Opportunités' du menu\n";
        $response .= "2. Vous verrez toutes les offres disponibles\n";
        $response .= "3. Cliquez sur une offre pour voir plus de détails\n\n";
        
        $response .= "MÉTHODE 3️⃣ - OFFRES COMPATIBLES:\n";
        $response .= "Si vous êtes connecté et avez défini vos préférences:\n";
        $response .= "• Allez à 'Offres Compatibles'\n";
        $response .= "• Vous verrez les offres qui correspondent à vos critères\n\n";
        
        if (count($offres) > 0) {
            $response .= "📊 ACTUELLEMENT, " . count($offres) . " OFFRES DISPONIBLES:\n";
            foreach ($offres as $offre) {
                $titre = $offre->getTitre() ?? 'Sans titre';
                $type = $offre->getTypeContrat() ?? 'Type non spécifié';
                $lieu = $offre->getLieu() ?? 'Non spécifié';
                $response .= "  ✓ $titre ($type) à $lieu\n";
            }
        } else {
            $response .= "⚠️ Aucune offre n'est actuellement disponible.";
        }
        
        return [
            'success' => true,
            'response' => $response,
            'message' => '',
            'fromServer' => true
        ];
    }

    /**
     * Explique comment modifier son profil
     */
    private function respondWithProfileManagement(): array
    {
        $response = "👤 COMMENT MODIFIER VOTRE PROFIL:\n\n";
        
        $response .= "POUR ÊTRE CONNECTÉ:\n";
        $response .= "1️⃣ Cliquez sur votre nom en haut à droite\n";
        $response .= "2️⃣ Sélectionnez 'Mon Profil' ou 'Paramètres de Compte'\n";
        $response .= "3️⃣ Cliquez sur 'Éditer' ou 'Modifier'\n";
        $response .= "4️⃣ Modifiez vos informations (nom, email, téléphone, CV, etc.)\n";
        $response .= "5️⃣ Sauvegardez les changements\n\n";
        
        $response .= "INFORMATIONS MODIFIABLES:\n";
        $response .= "✏️ Informations personnelles (nom, email, téléphone)\n";
        $response .= "✏️ Photo de profil\n";
        $response .= "✏️ CV et lettres de motivation\n";
        $response .= "✏️ Compétences et expérience\n";
        $response .= "✏️ Préférences de recherche d'emploi\n";
        $response .= "✏️ Mode de travail préféré (remote, hybride, sur site)\n";
        $response .= "✏️ Prétention salariale\n\n";
        
        $response .= "⚠️ IMPORTANT:\n";
        $response .= "• Gardez votre profil à jour pour de meilleures propositions\n";
        $response .= "• Un profil complet augmente vos chances d'être sélectionné\n";
        $response .= "• Vous devez être connecté pour modifier votre profil";
        
        return [
            'success' => true,
            'response' => $response,
            'message' => '',
            'fromServer' => true
        ];
    }

    /**
     * Explique comment suivre sa candidature
     */
    private function respondWithApplicationTracking(?User $user): array
    {
        if (!$user) {
            return [
                'success' => true,
                'response' => '📧 Pour suivre vos candidatures, veuillez vous connecter à votre compte.' . "\n\n" .
                    'Une fois connecté:\n' .
                    '1. Allez à la section "Mes Candidatures"\n' .
                    '2. Vous verrez le statut de chaque candidature:\n' .
                    '   ⏳ En attente - Votre candidature est en cours d\'examen\n' .
                    '   ✅ Acceptée - Félicitations! Vous avez été sélectionné\n' .
                    '   ❌ Refusée - La candidature n\'a pas été retenue\n' .
                    '3. Cliquez sur une candidature pour plus de détails',
                'message' => '',
                'fromServer' => true
            ];
        }
        
        $response = "📧 COMMENT SUIVRE VOS CANDIDATURES:\n\n";
        $response .= "ÉTAPES:\n";
        $response .= "1️⃣ Connectez-vous à votre compte\n";
        $response .= "2️⃣ Allez à 'Mes Candidatures' ou 'Candidatures' en haut du menu\n";
        $response .= "3️⃣ Vous verrez toutes vos candidatures avec leur statut:\n\n";
        
        $response .= "STATUTS POSSIBLES:\n";
        $response .= "⏳ EN ATTENTE - Votre candidature est en cours d'examen par le recruteur\n";
        $response .= "✅ ACCEPTÉE - Félicitations! Vous avez été sélectionné pour l'entretien\n";
        $response .= "❌ REFUSÉE - La candidature n'a pas été retenue\n";
        $response .= "📅 ENTRETIEN PRÉVU - Vous avez un entretien programmé\n";
        $response .= "✔️ CONTRAT SIGNÉ - Vous avez un contrat d'embauche\n\n";
        
        $response .= "ACTIONS SUPPLÉMENTAIRES:\n";
        $response .= "• Cliquez sur une candidature pour voir les détails de l'offre\n";
        $response .= "• Accédez aux retours du recruteur si disponibles\n";
        $response .= "• Recevez des notifications par email pour chaque changement de statut\n\n";
        
        $response .= "💡 CONSEIL: Vérifiez régulièrement vos candidatures et n'hésitez pas à vous reconnecter aux offres qui vous intéressent!";
        
        return [
            'success' => true,
            'response' => $response,
            'message' => '',
            'fromServer' => true
        ];
    }

    /**
     * Répond aux questions de définition/explication sur les métiers
     */
    private function respondWithDefinition(string $message): array
    {
        $messageLower = strtolower($message);
        
        // Dictionnaire de définitions des métiers
        $definitions = [
            'frontend' => [
                'keywords' => ['frontend', 'front-end', 'front end'],
                'definition' => "💻 DÉVELOPPEUR FRONTEND\n\n" .
                    "Un développeur Frontend est responsable de la création de l'interface utilisateur (UI) d'une application web ou mobile.\n\n" .
                    "🎯 RESPONSABILITÉS:\n" .
                    "• Créer des interfaces utilisateur attrayantes et réactives\n" .
                    "• Programmer avec HTML, CSS et JavaScript\n" .
                    "• Utiliser des frameworks modernes (React, Vue, Angular)\n" .
                    "• Optimiser la performance et l'accessibilité\n" .
                    "• Collaborer avec les designers et le backend\n\n" .
                    "🛠️ COMPÉTENCES CLÉS:\n" .
                    "• Maîtrise de HTML, CSS et JavaScript\n" .
                    "• Frameworks frontend (React, Vue, Angular)\n" .
                    "• Responsive design et accessibilité\n" .
                    "• Git et outils de développement\n\n" .
                    "💰 SALAIRE: Entre 1200-2000 TND/mois selon expérience\n\n" .
                    "💼 Consultez nos offres Frontend disponibles: demandez 'offres disponibles'"
            ],
            'backend' => [
                'keywords' => ['backend', 'back-end', 'back end', 'serveur'],
                'definition' => "⚙️ DÉVELOPPEUR BACKEND\n\n" .
                    "Un développeur Backend crée la logique serveur et les APIs qui supportent l'application.\n\n" .
                    "🎯 RESPONSABILITÉS:\n" .
                    "• Développer les APIs REST et services web\n" .
                    "• Gérer les bases de données\n" .
                    "• Implémenter la logique métier\n" .
                    "• Gérer l'authentification et la sécurité\n" .
                    "• Optimiser les performances\n\n" .
                    "🛠️ COMPÉTENCES CLÉS:\n" .
                    "• PHP, Python, Java, Node.js\n" .
                    "• Frameworks (Symfony, Laravel, Django, Spring)\n" .
                    "• Bases de données (MySQL, PostgreSQL, MongoDB)\n" .
                    "• APIs REST et services web\n\n" .
                    "💰 SALAIRE: Entre 1400-2200 TND/mois selon expérience"
            ],
            'fullstack' => [
                'keywords' => ['full stack', 'fullstack', 'full-stack'],
                'definition' => "🔄 DÉVELOPPEUR FULL STACK\n\n" .
                    "Un développeur Full Stack maîtrise à la fois le Frontend et le Backend.\n\n" .
                    "🎯 AVANTAGES:\n" .
                    "• Compréhension complète de l'application\n" .
                    "• Plus d'opportunités de carrière\n" .
                    "• Possibilité de gérer des projets entiers\n\n" .
                    "🛠️ COMPÉTENCES REQUISES:\n" .
                    "• Toutes compétences Frontend + Backend\n" .
                    "• Outils et DevOps basiques\n" .
                    "• Capacité à apprendre rapidement\n\n" .
                    "💰 SALAIRE: Entre 1600-2500 TND/mois selon expérience"
            ],
            'data' => [
                'keywords' => ['data', 'analyste', 'analyst'],
                'definition' => "📊 DATA ANALYST\n\n" .
                    "Un Data Analyst analyse les données pour aider à la prise de décision.\n\n" .
                    "🎯 RESPONSABILITÉS:\n" .
                    "• Collecter et analyser les données\n" .
                    "• Créer des rapports et visualisations\n" .
                    "• Identifier des tendances et insights\n" .
                    "• Recommander des améliorations\n\n" .
                    "🛠️ COMPÉTENCES CLÉS:\n" .
                    "• SQL et Excel avancé\n" .
                    "• Python ou R\n" .
                    "• Outils BI (Power BI, Tableau)\n" .
                    "• Compréhension statistique\n\n" .
                    "💰 SALAIRE: Entre 1300-2000 TND/mois selon expérience"
            ],
            'qa' => [
                'keywords' => ['qa', 'test', 'tester', 'qualité'],
                'definition' => "🧪 QA TESTER\n\n" .
                    "Un QA Tester assure la qualité et la fiabilité des applications.\n\n" .
                    "🎯 RESPONSABILITÉS:\n" .
                    "• Tester les applications manuellement et automatiquement\n" .
                    "• Identifier et documenter les bugs\n" .
                    "• Créer des plans de test\n" .
                    "• Vérifier la compatibilité et la performance\n\n" .
                    "🛠️ COMPÉTENCES CLÉS:\n" .
                    "• Test manual et automatisé\n" .
                    "• Outils: Selenium, Jira, TestNG\n" .
                    "• Compréhension de SDLC\n" .
                    "• Attention aux détails\n\n" .
                    "💰 SALAIRE: Entre 1000-1600 TND/mois selon expérience"
            ],
            'ui' => [
                'keywords' => ['ui', 'ux', 'designer', 'design'],
                'definition' => "🎨 UI/UX DESIGNER\n\n" .
                    "Un UI/UX Designer crée l'expérience utilisateur et l'interface visuelle.\n\n" .
                    "🎯 RESPONSABILITÉS:\n" .
                    "• Créer des wireframes et mockups\n" .
                    "• Concevoir une expérience utilisateur intuitive\n" .
                    "• Collaborer avec développeurs et product managers\n" .
                    "• Tester et itérer les designs\n\n" .
                    "🛠️ COMPÉTENCES CLÉS:\n" .
                    "• Figma, Adobe XD, Sketch\n" .
                    "• Principes de design UX\n" .
                    "• Prototypage\n" .
                    "• Connaissance HTML/CSS\n\n" .
                    "💰 SALAIRE: Entre 1200-2000 TND/mois selon expérience"
            ],
            'mobile' => [
                'keywords' => ['mobile', 'app', 'application'],
                'definition' => "📱 DÉVELOPPEUR MOBILE\n\n" .
                    "Un développeur Mobile crée des applications pour smartphones et tablettes.\n\n" .
                    "🎯 SPECIALISATIONS:\n" .
                    "• iOS (Swift)\n" .
                    "• Android (Kotlin, Java)\n" .
                    "• Cross-platform (Flutter, React Native)\n\n" .
                    "🎯 RESPONSABILITÉS:\n" .
                    "• Développer et déployer des apps mobiles\n" .
                    "• Optimiser pour différents appareils\n" .
                    "• Gérer les APIs et données\n" .
                    "• Tester sur vrais appareils\n\n" .
                    "🛠️ COMPÉTENCES CLÉS:\n" .
                    "• Swift/Kotlin ou Flutter/React Native\n" .
                    "• APIs REST\n" .
                    "• Version control (Git)\n\n" .
                    "💰 SALAIRE: Entre 1400-2200 TND/mois selon expérience"
            ],
            'devops' => [
                'keywords' => ['devops', 'dev ops', 'sre'],
                'definition' => "🚀 DEVOPS ENGINEER\n\n" .
                    "Un DevOps Engineer assure le déploiement, la maintenance et la scalabilité.\n\n" .
                    "🎯 RESPONSABILITÉS:\n" .
                    "• Automatiser les déploiements\n" .
                    "• Gérer l'infrastructure cloud\n" .
                    "• Assurer la disponibilité et la performance\n" .
                    "• Implémenter CI/CD\n\n" .
                    "🛠️ COMPÉTENCES CLÉS:\n" .
                    "• Linux, Docker, Kubernetes\n" .
                    "• Cloud (AWS, Azure, GCP)\n" .
                    "• Jenkins, GitLab CI\n" .
                    "• Scripting (Bash, Python)\n\n" .
                    "💰 SALAIRE: Entre 1600-2500 TND/mois selon expérience"
            ]
        ];
        
        // Chercher la définition appropriée
        foreach ($definitions as $key => $def) {
            foreach ($def['keywords'] as $keyword) {
                if (stripos($messageLower, $keyword) !== false) {
                    return [
                        'success' => true,
                        'response' => $def['definition'],
                        'message' => '',
                        'fromServer' => true
                    ];
                }
            }
        }
        
        // Si aucune définition spécifique ne correspond, répondre générallement
        $response = "📚 DÉFINITIONS DE MÉTIERS EN INFORMATIQUE:\n\n";
        $response .= "Je peux vous expliquer les métiers suivants:\n";
        $response .= "• Frontend Developer - Développeur Frontend\n";
        $response .= "• Backend Developer - Développeur Backend\n";
        $response .= "• Full Stack Developer - Développeur Full Stack\n";
        $response .= "• Data Analyst - Analyste de Données\n";
        $response .= "• QA Tester - Testeur QA\n";
        $response .= "• UI/UX Designer - Designer UI/UX\n";
        $response .= "• Mobile Developer - Développeur Mobile\n";
        $response .= "• DevOps Engineer - Ingénieur DevOps\n\n";
        $response .= "Posez votre question de manière plus précise, par exemple: 'Qu'est-ce qu'un développeur frontend?'";
        
        return [
            'success' => true,
            'response' => $response,
            'message' => '',
            'fromServer' => true
        ];
    }

    /**
     * Obtient des suggestions basées sur le message de l'utilisateur
     */
    public function getSuggestions(string $query): array
    {
        $suggestions = [];

        // Suggestions sur les offres d'emploi
        if (stripos($query, 'offre') !== false || stripos($query, 'emploi') !== false) {
            $offresRepository = $this->entityManager->getRepository(OffreEmploi::class);
            $offres = $offresRepository->findBy(['statutOffre' => 'OUVERTE'], [], 5);
            
            foreach ($offres as $offre) {
                $suggestions[] = [
                    'type' => 'offre',
                    'title' => $offre->getTitre(),
                    'description' => $offre->getLieu()
                ];
            }
        }

        return $suggestions;
    }
}

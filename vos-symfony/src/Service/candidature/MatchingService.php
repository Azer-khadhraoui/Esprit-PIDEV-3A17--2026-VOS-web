<?php

namespace App\Service\candidature;

use App\Entity\OffreEmploi;
use App\Entity\PreferenceCandidature;

class MatchingService
{
    /**
     * Calcule le score de matching entre une offre et les préférences d'un candidat
     * 
     * @return array{
     *     score: float,
     *     percentage: float,
     *     quality: string,
     *     color: string,
     *     criteria: array,
     *     recommendations: array
     * }
     */
    public function calculateMatching(OffreEmploi $offre, ?PreferenceCandidature $preference): array
    {
        $criteria = [];
        $totalScore = 0;
        
        // Si pas de préférence, retourner un résultat neutre
        if (!$preference) {
            return $this->getNeutralResult();
        }

        // 1. Matching Type de Poste (30%)
        $typeScore = $this->matchJobType($offre, $preference);
        $criteria['typePoste'] = [
            'label' => '👔 Type de Poste',
            'score' => $typeScore['score'],
            'weight' => 30,
            'explanation' => $typeScore['explanation'],
            'status' => $typeScore['status']
        ];
        $totalScore += $typeScore['score'] * 0.30;

        // 2. Matching Mode de Travail (25%)
        $modeScore = $this->matchWorkMode($offre, $preference);
        $criteria['modeTravail'] = [
            'label' => '🌍 Mode de Travail',
            'score' => $modeScore['score'],
            'weight' => 25,
            'explanation' => $modeScore['explanation'],
            'status' => $modeScore['status']
        ];
        $totalScore += $modeScore['score'] * 0.25;

        // 3. Matching Disponibilité (20%)
        $disponibiliteScore = $this->matchAvailability($offre, $preference);
        $criteria['disponibilite'] = [
            'label' => '📅 Disponibilité',
            'score' => $disponibiliteScore['score'],
            'weight' => 20,
            'explanation' => $disponibiliteScore['explanation'],
            'status' => $disponibiliteScore['status']
        ];
        $totalScore += $disponibiliteScore['score'] * 0.20;

        // 4. Matching Type de Contrat (15%)
        $typeContratScore = $this->matchContractType($offre, $preference);
        $criteria['typeContrat'] = [
            'label' => '📝 Type de Contrat',
            'score' => $typeContratScore['score'],
            'weight' => 15,
            'explanation' => $typeContratScore['explanation'],
            'status' => $typeContratScore['status']
        ];
        $totalScore += $typeContratScore['score'] * 0.15;

        // 5. Matching Lieu (10%)
        $lieuScore = $this->matchLocation($offre, $preference);
        $criteria['lieu'] = [
            'label' => '📍 Lieu',
            'score' => $lieuScore['score'],
            'weight' => 10,
            'explanation' => $lieuScore['explanation'],
            'status' => $lieuScore['status']
        ];
        $totalScore += $lieuScore['score'] * 0.10;

        // Générer les recommandations
        $recommendations = $this->generateRecommendations($criteria, $preference, $offre);

        // Déterminer la qualité du matching
        $quality = $this->getQualityLevel($totalScore);
        $color = $this->getColorByScore($totalScore);

        return [
            'score' => round($totalScore, 2),
            'percentage' => round($totalScore),
            'quality' => $quality,
            'color' => $color,
            'criteria' => $criteria,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Matching pour le type de poste
     */
    private function matchJobType(OffreEmploi $offre, PreferenceCandidature $preference): array
    {
        $preferredType = $preference->getTypePosteSouhaite();
        $offreTitle = $offre->getTitre();

        if (!$preferredType) {
            return [
                'score' => 50,
                'explanation' => 'Pas de type de poste spécifié',
                'status' => 'neutral'
            ];
        }

        // Vérifier si le type souhaité est mentionné dans le titre
        $keywordsPreferred = array_map('trim', explode(',', mb_strtolower($preferredType)));
        $titleLower = mb_strtolower($offreTitle ?? '');

        $matchCount = 0;
        foreach ($keywordsPreferred as $keyword) {
            if (!empty($keyword) && str_contains($titleLower, $keyword)) {
                $matchCount++;
            }
        }

        if ($matchCount === count($keywordsPreferred)) {
            return [
                'score' => 100,
                'explanation' => "Excellent ! Le poste correspond exactement à votre recherche",
                'status' => 'perfect'
            ];
        } elseif ($matchCount > 0) {
            return [
                'score' => 70,
                'explanation' => "Bon match avec votre type de poste souhaité",
                'status' => 'good'
            ];
        }

        return [
            'score' => 30,
            'explanation' => "Le type de poste diffère de vos préférences",
            'status' => 'poor'
        ];
    }

    /**
     * Matching pour le mode de travail
     */
    private function matchWorkMode(OffreEmploi $offre, PreferenceCandidature $preference): array
    {
        $preferredMode = $preference->getModeTravail();
        $offreMode = $offre->getWorkPreference();

        if (!$preferredMode || !$offreMode) {
            return [
                'score' => 50,
                'explanation' => 'Mode de travail non spécifié',
                'status' => 'neutral'
            ];
        }

        // Normaliser les valeurs
        $pMode = mb_strtolower($preferredMode);
        $oMode = mb_strtolower($offreMode);

        if ($pMode === $oMode) {
            return [
                'score' => 100,
                'explanation' => "Parfait ! Le mode de travail correspond à votre préférence : {$offreMode}",
                'status' => 'perfect'
            ];
        }

        // Hybride peut correspondre à Présentiel
        if (str_contains($pMode, 'hybride') && str_contains($oMode, 'présentiel')) {
            return [
                'score' => 75,
                'explanation' => "Acceptable. Vous préférez un mode hybride mais l'offre est en présentiel",
                'status' => 'good'
            ];
        }

        return [
            'score' => 20,
            'explanation' => "Mode de travail incompatible avec votre préférence",
            'status' => 'poor'
        ];
    }

    /**
     * Matching pour la disponibilité
     */
    private function matchAvailability(OffreEmploi $offre, PreferenceCandidature $preference): array
    {
        $dateDisponibilite = $preference->getDateDisponibilite();
        $disponibilite = $preference->getDisponibilite();

        if (!$disponibilite && !$dateDisponibilite) {
            return [
                'score' => 50,
                'explanation' => 'Disponibilité non spécifiée',
                'status' => 'neutral'
            ];
        }

        $today = new \DateTime('today');

        // Vérifier si l'utilisateur est disponible immédiatement
        if ($disponibilite === 'Immédiatement') {
            return [
                'score' => 100,
                'explanation' => "Vous êtes disponible immédiatement. Parfait pour cette offre !",
                'status' => 'perfect'
            ];
        }

        // Vérifier si la date de disponibilité est dans un délai raisonnable
        if ($dateDisponibilite) {
            $interval = $today->diff($dateDisponibilite);
            $days = $interval->days;

            if ($days <= 30) {
                return [
                    'score' => 90,
                    'explanation' => "Vous serez disponible très bientôt (dans {$days} jours)",
                    'status' => 'perfect'
                ];
            } elseif ($days <= 90) {
                return [
                    'score' => 70,
                    'explanation' => "Vous serez disponible dans environ " . ceil($days / 30) . " mois",
                    'status' => 'good'
                ];
            }
        }

        return [
            'score' => 50,
            'explanation' => "Disponibilité dans un délai plus long",
            'status' => 'neutral'
        ];
    }

    /**
     * Matching pour le type de contrat
     */
    private function matchContractType(OffreEmploi $offre, PreferenceCandidature $preference): array
    {
        $preferredContract = $preference->getTypeContratSouhaite();
        $offreContract = $offre->getTypeContrat();

        if (!$preferredContract || !$offreContract) {
            return [
                'score' => 50,
                'explanation' => 'Type de contrat non spécifié',
                'status' => 'neutral'
            ];
        }

        if (mb_strtolower($preferredContract) === mb_strtolower($offreContract)) {
            return [
                'score' => 100,
                'explanation' => "Type de contrat parfait : {$offreContract}",
                'status' => 'perfect'
            ];
        }

        return [
            'score' => 30,
            'explanation' => "Type de contrat différent de votre préférence",
            'status' => 'poor'
        ];
    }

    /**
     * Matching pour le lieu
     */
    private function matchLocation(OffreEmploi $offre, PreferenceCandidature $preference): array
    {
        $mobilite = $preference->getMobiliteGeographique();
        $offreLieu = $offre->getLieu();

        if (!$mobilite || !$offreLieu) {
            return [
                'score' => 50,
                'explanation' => 'Localisation non spécifiée',
                'status' => 'neutral'
            ];
        }

        // Si l'utilisateur n'accepte pas les déplacements
        if ($mobilite === 'Non') {
            return [
                'score' => 20,
                'explanation' => "Vous n'acceptez pas les déplacements géographiques",
                'status' => 'poor'
            ];
        }

        // Si national accepté
        if ($mobilite === 'Oui, national') {
            return [
                'score' => 85,
                'explanation' => "Vous acceptez la mobilité nationale",
                'status' => 'good'
            ];
        }

        // Si région acceptée
        if ($mobilite === 'Oui, région') {
            return [
                'score' => 70,
                'explanation' => "Vous acceptez la mobilité régionale",
                'status' => 'good'
            ];
        }

        return [
            'score' => 50,
            'explanation' => 'Compatibilité de localisation à vérifier',
            'status' => 'neutral'
        ];
    }

    /**
     * Générer les recommandations
     */
    private function generateRecommendations(array $criteria, PreferenceCandidature $preference, OffreEmploi $offre): array
    {
        $recommendations = [];

        foreach ($criteria as $key => $criterion) {
            if ($criterion['score'] < 70) {
                $recommendations[] = [
                    'criterion' => $criterion['label'],
                    'message' => $criterion['explanation'],
                    'priority' => $criterion['score'] < 40 ? 'high' : 'medium'
                ];
            }
        }

        // Ajouter des recommandations générales
        if (count($recommendations) === 0) {
            $recommendations[] = [
                'criterion' => '✅ Excellent match',
                'message' => 'Cette offre correspond parfaitement à vos préférences. N\'hésitez pas à postuler !',
                'priority' => 'positive'
            ];
        }

        return $recommendations;
    }

    /**
     * Obtenir le niveau de qualité du match
     */
    private function getQualityLevel(float $score): string
    {
        if ($score >= 85) {
            return 'Excellent';
        } elseif ($score >= 70) {
            return 'Très Bon';
        } elseif ($score >= 55) {
            return 'Bon';
        } elseif ($score >= 40) {
            return 'Acceptable';
        }

        return 'Faible';
    }

    /**
     * Obtenir la couleur selon le score
     */
    private function getColorByScore(float $score): string
    {
        if ($score >= 85) {
            return '#06d6a0'; // Vert
        } elseif ($score >= 70) {
            return '#00b4d8'; // Bleu
        } elseif ($score >= 55) {
            return '#ffd60a'; // Jaune
        } elseif ($score >= 40) {
            return '#ff9500'; // Orange
        }

        return '#ef476f'; // Rouge
    }

    /**
     * Résultat neutre quand pas de préférence
     */
    private function getNeutralResult(): array
    {
        return [
            'score' => 50,
            'percentage' => 50,
            'quality' => 'Non évalué',
            'color' => '#a8a8a8',
            'criteria' => [],
            'recommendations' => [
                [
                    'criterion' => 'ℹ️ Pas de préférences définie',
                    'message' => 'Veuillez créer votre profil de préférences pour obtenir une évaluation personnalisée',
                    'priority' => 'info'
                ]
            ]
        ];
    }
}

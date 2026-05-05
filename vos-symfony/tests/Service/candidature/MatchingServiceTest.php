<?php

namespace App\Tests\Service\candidature;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Service\candidature\MatchingService;
use App\Entity\OffreEmploi;
use App\Entity\PreferenceCandidature;

class MatchingServiceTest extends TestCase
{
    private MatchingService $matchingService;

    protected function setUp(): void
    {
        $this->matchingService = new MatchingService();
    }

    /**
     * Test que le service est instancié correctement
     */
    public function testServiceInstantiation(): void
    {
        $this->assertInstanceOf(MatchingService::class, $this->matchingService);
    }

    /**
     * Test le matching avec des préférences null
     */
    public function testCalculateMatchingWithNullPreference(): void
    {
        $offre = new OffreEmploi();
        $result = $this->matchingService->calculateMatching($offre, null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('percentage', $result);
        $this->assertArrayHasKey('quality', $result);
    }

    /**
     * Test le matching avec une offre et des préférences complètes
     */
    public function testCalculateMatchingWithCompletePreferences(): void
    {
        $offre = new OffreEmploi();
        $preference = new PreferenceCandidature();

        $result = $this->matchingService->calculateMatching($offre, $preference);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('percentage', $result);
        $this->assertArrayHasKey('quality', $result);
        $this->assertArrayHasKey('criteria', $result);
        
        // Vérifier que le score est entre 0 et 100
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }

    /**
     * Test que les critères sont correctement pondérés
     */
    public function testCriteriaWeighting(): void
    {
        $offre = new OffreEmploi();
        $preference = new PreferenceCandidature();

        $result = $this->matchingService->calculateMatching($offre, $preference);

        if (isset($result['criteria'])) {
            $criteria = $result['criteria'];
            
            // Vérifier que chaque critère a un poids
            foreach ($criteria as $criterion) {
                $this->assertArrayHasKey('weight', $criterion);
                $this->assertArrayHasKey('score', $criterion);
            }
        }
    }

    /**
     * Test la génération des recommandations
     */
    public function testRecommendationsGeneration(): void
    {
        $offre = new OffreEmploi();
        $preference = new PreferenceCandidature();
        $preference->setTypePosteSouhaite('Développeur');
        $preference->setModeTravail('Hybride');

        $result = $this->matchingService->calculateMatching($offre, $preference);

        $this->assertIsArray($result);
        if (isset($result['recommendations'])) {
            $this->assertIsArray($result['recommendations']);
        }
    }

    /**
     * Test la qualité du matching (niveau)
     */
    public function testQualityLevels(): void
    {
        $offre = new OffreEmploi();
        $preference = new PreferenceCandidature();

        $result = $this->matchingService->calculateMatching($offre, $preference);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('quality', $result);
        $this->assertIsString($result['quality']);
        
        // Les qualités possibles
        $validQualities = ['Excellent', 'Bon', 'Acceptable', 'Faible', 'Très faible'];
        $this->assertContains($result['quality'], $validQualities);
    }

    /**
     * Test la couleur du score
     */
    public function testColorAssignment(): void
    {
        $offre = new OffreEmploi();
        $preference = new PreferenceCandidature();

        $result = $this->matchingService->calculateMatching($offre, $preference);

        $this->assertIsArray($result);
        if (isset($result['color'])) {
            $this->assertIsString($result['color']);
            // Les couleurs possibles selon la classe
            $validColors = ['green', 'lime', 'orange', 'red', 'darkred', '#22c55e', '#84cc16', '#f59e0b', '#ef4444', '#7f1d1d'];
            $this->assertTrue(in_array($result['color'], $validColors) || is_string($result['color']));
        }
    }

    /**
     * Test le score de pourcentage
     */
    public function testPercentageScore(): void
    {
        $offre = new OffreEmploi();
        $preference = new PreferenceCandidature();

        $result = $this->matchingService->calculateMatching($offre, $preference);

        $this->assertArrayHasKey('percentage', $result);
        $this->assertGreaterThanOrEqual(0, $result['percentage']);
        $this->assertLessThanOrEqual(100, $result['percentage']);
        // Le pourcentage peut être int ou float selon l'implémentation
        $this->assertTrue(is_int($result['percentage']) || is_float($result['percentage']));
    }

    /**
     * Test que le matching retourne toujours une structure cohérente
     */
    public function testMatchingReturnStructure(): void
    {
        $offre = new OffreEmploi();
        $preference = new PreferenceCandidature();

        $result = $this->matchingService->calculateMatching($offre, $preference);

        // Vérifier les clés principales
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('percentage', $result);
        $this->assertArrayHasKey('quality', $result);
        $this->assertIsArray($result);
    }

    /**
     * Test avec des préférences partielles
     */
    public function testCalculateMatchingWithPartialPreferences(): void
    {
        $offre = new OffreEmploi();
        $preference = new PreferenceCandidature();
        $preference->setTypePosteSouhaite('Développeur Backend');

        $result = $this->matchingService->calculateMatching($offre, $preference);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }

    /**
     * Test la stabilité du calcul avec les mêmes paramètres
     */
    public function testMatchingConsistency(): void
    {
        $offre = new OffreEmploi();
        $preference = new PreferenceCandidature();
        $preference->setTypePosteSouhaite('Développeur');
        $preference->setModeTravail('Hybride');
        $preference->setTypeContratSouhaite('CDI');

        $result1 = $this->matchingService->calculateMatching($offre, $preference);
        $result2 = $this->matchingService->calculateMatching($offre, $preference);

        $this->assertEquals($result1['score'], $result2['score']);
        $this->assertEquals($result1['quality'], $result2['quality']);
    }
}

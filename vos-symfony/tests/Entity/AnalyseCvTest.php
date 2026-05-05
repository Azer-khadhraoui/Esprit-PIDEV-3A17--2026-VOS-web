<?php

namespace App\Tests\Entity;

use PHPUnit\Framework\TestCase;
use App\Entity\AnalyseCv;
use App\Entity\Candidature;
use DateTime;

class AnalyseCvTest extends TestCase
{
    private AnalyseCv $analyseCv;

    protected function setUp(): void
    {
        $this->analyseCv = new AnalyseCv();
    }

    /**
     * Test du constructeur - la date d'analyse doit être initialisée maintenant
     */
    public function testConstructorSetsCurrentDate(): void
    {
        $now = new DateTime();
        $analyseCv = new AnalyseCv();
        
        $this->assertNotNull($analyseCv->getDateAnalyse());
        $this->assertLessThanOrEqual(5, abs($now->getTimestamp() - $analyseCv->getDateAnalyse()->getTimestamp()));
    }

    /**
     * Test des getters/setters pour l'ID de candidature
     */
    public function testSetAndGetIdCandidature(): void
    {
        $this->analyseCv->setIdCandidature(42);
        $this->assertEquals(42, $this->analyseCv->getIdCandidature());
    }

    /**
     * Test des getters/setters pour la candidature
     */
    public function testSetAndGetCandidature(): void
    {
        $candidature = new Candidature();
        $this->analyseCv->setCandidature($candidature);
        $this->assertEquals($candidature, $this->analyseCv->getCandidature());
    }

    /**
     * Test que setCandidature met à jour aussi l'ID
     */
    public function testSetCandidatureUpdatesIdCandidature(): void
    {
        $candidature = new Candidature();
        $reflectionClass = new \ReflectionClass($candidature);
        $property = $reflectionClass->getProperty('id_candidature');
        $property->setAccessible(true);
        $property->setValue($candidature, 123);
        
        $this->analyseCv->setCandidature($candidature);
        $this->assertEquals(123, $this->analyseCv->getIdCandidature());
    }

    /**
     * Test des getters/setters pour les compétences détectées
     */
    public function testSetAndGetCompetencesDetectees(): void
    {
        $competences = [
            'PHP' => 85,
            'Symfony' => 80,
            'MySQL' => 75,
            'Docker' => 70
        ];
        
        $this->analyseCv->setCompetencesDetectees($competences);
        $this->assertEquals($competences, $this->analyseCv->getCompetencesDetectees());
    }

    /**
     * Test des getters/setters pour les points forts
     */
    public function testSetAndGetPointsForts(): void
    {
        $pointsForts = [
            'Excellente compréhension des architectures',
            'Leadership confirmé',
            'Mentalité agile'
        ];
        
        $this->analyseCv->setPointsForts($pointsForts);
        $this->assertEquals($pointsForts, $this->analyseCv->getPointsForts());
    }

    /**
     * Test des getters/setters pour les points faibles
     */
    public function testSetAndGetPointsFaibles(): void
    {
        $pointsFaibles = [
            'Peu d\'expérience en cloud computing',
            'Compétences en DevOps limitées'
        ];
        
        $this->analyseCv->setPointsFaibles($pointsFaibles);
        $this->assertEquals($pointsFaibles, $this->analyseCv->getPointsFaibles());
    }

    /**
     * Test des getters/setters pour le score du CV
     */
    public function testSetAndGetScoreCv(): void
    {
        $this->analyseCv->setScoreCv(85);
        $this->assertEquals(85, $this->analyseCv->getScoreCv());
    }

    /**
     * Test score CV à 0
     */
    public function testScoreCvZero(): void
    {
        $this->analyseCv->setScoreCv(0);
        $this->assertEquals(0, $this->analyseCv->getScoreCv());
    }

    /**
     * Test score CV à 100
     */
    public function testScoreCvMax(): void
    {
        $this->analyseCv->setScoreCv(100);
        $this->assertEquals(100, $this->analyseCv->getScoreCv());
    }

    /**
     * Test des getters/setters pour les suggestions
     */
    public function testSetAndGetSuggestions(): void
    {
        $suggestions = [
            'Améliorez vos compétences en cloud',
            'Ajoutez des certifications Docker',
            'Renforcez l\'expérience DevOps'
        ];
        
        $this->analyseCv->setSuggestions($suggestions);
        $this->assertEquals($suggestions, $this->analyseCv->getSuggestions());
    }

    /**
     * Test des getters/setters pour la date d'analyse
     */
    public function testSetAndGetDateAnalyse(): void
    {
        $date = new DateTime('2025-02-15 10:30:00');
        $this->analyseCv->setDateAnalyse($date);
        $this->assertEquals($date, $this->analyseCv->getDateAnalyse());
    }

    /**
     * Test que le getter retourne null au départ pour ID analyse
     */
    public function testGetIdAnalyseIsNullInitially(): void
    {
        $this->assertNull($this->analyseCv->getIdAnalyse());
    }

    /**
     * Test d'initialisation complète d'une analyse
     */
    public function testCompleteAnalysisInitialization(): void
    {
        $date = new DateTime('2025-02-20');
        
        $competences = ['PHP' => 90, 'Symfony' => 85];
        $pointsForts = ['Expérience confirmée', 'Bon communicateur'];
        $pointsFaibles = ['Pas de DevOps'];
        $suggestions = ['Apprendre Docker'];
        
        $this->analyseCv->setIdCandidature(10);
        $this->analyseCv->setCompetencesDetectees($competences);
        $this->analyseCv->setPointsForts($pointsForts);
        $this->analyseCv->setPointsFaibles($pointsFaibles);
        $this->analyseCv->setScoreCv(88);
        $this->analyseCv->setSuggestions($suggestions);
        $this->analyseCv->setDateAnalyse($date);

        $this->assertEquals(10, $this->analyseCv->getIdCandidature());
        $this->assertEquals($competences, $this->analyseCv->getCompetencesDetectees());
        $this->assertEquals($pointsForts, $this->analyseCv->getPointsForts());
        $this->assertEquals($pointsFaibles, $this->analyseCv->getPointsFaibles());
        $this->assertEquals(88, $this->analyseCv->getScoreCv());
        $this->assertEquals($suggestions, $this->analyseCv->getSuggestions());
        $this->assertEquals($date, $this->analyseCv->getDateAnalyse());
    }

    /**
     * Test que les setters retournent $this pour le chaînage
     */
    public function testSettersReturnStaticForFluentInterface(): void
    {
        $result = $this->analyseCv->setScoreCv(75);
        $this->assertInstanceOf(AnalyseCv::class, $result);
        
        $result = $this->analyseCv->setCompetencesDetectees(['PHP' => 80]);
        $this->assertInstanceOf(AnalyseCv::class, $result);
    }

    /**
     * Test nullable values
     */
    public function testNullableValues(): void
    {
        $analyseCv = new AnalyseCv();
        
        $this->assertNull($analyseCv->getIdCandidature());
        $this->assertNull($analyseCv->getCandidature());
        $this->assertNull($analyseCv->getCompetencesDetectees());
        $this->assertNull($analyseCv->getPointsForts());
        $this->assertNull($analyseCv->getPointsFaibles());
        $this->assertNull($analyseCv->getScoreCv());
        $this->assertNull($analyseCv->getSuggestions());
        $this->assertNotNull($analyseCv->getDateAnalyse());
    }

    /**
     * Test avec des structures JSON complexes
     */
    public function testComplexJsonStructures(): void
    {
        $competencesComplex = [
            'langages' => ['PHP', 'Python', 'JavaScript'],
            'frameworks' => ['Symfony', 'Django', 'React'],
            'outils' => ['Docker', 'Git', 'Kubernetes']
        ];
        
        $this->analyseCv->setCompetencesDetectees($competencesComplex);
        $this->assertEquals($competencesComplex, $this->analyseCv->getCompetencesDetectees());
    }
}

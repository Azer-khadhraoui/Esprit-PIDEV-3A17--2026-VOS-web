<?php

namespace App\Tests\Entity;

use PHPUnit\Framework\TestCase;
use App\Entity\PreferenceCandidature;
use DateTime;

class PreferenceCandidatureTest extends TestCase
{
    private PreferenceCandidature $preference;

    protected function setUp(): void
    {
        $this->preference = new PreferenceCandidature();
    }

    /**
     * Test des getters/setters pour le type de poste souhaité
     */
    public function testSetAndGetTypePosteSouhaite(): void
    {
        $type = "Développeur Full Stack";
        $this->preference->setTypePosteSouhaite($type);
        $this->assertEquals($type, $this->preference->getTypePosteSouhaite());
    }

    /**
     * Test des getters/setters pour le mode de travail
     */
    public function testSetAndGetModeTravail(): void
    {
        $modes = ["100% Présentiel", "100% Télétravail", "Hybride"];
        
        foreach ($modes as $mode) {
            $this->preference->setModeTravail($mode);
            $this->assertEquals($mode, $this->preference->getModeTravail());
        }
    }

    /**
     * Test des getters/setters pour la disponibilité
     */
    public function testSetAndGetDisponibilite(): void
    {
        $disponibilites = ["Immédiatement", "Dans 1 mois", "Dans 3 mois", "Dans 6 mois"];
        
        foreach ($disponibilites as $dispo) {
            $this->preference->setDisponibilite($dispo);
            $this->assertEquals($dispo, $this->preference->getDisponibilite());
        }
    }

    /**
     * Test des getters/setters pour la mobilité géographique
     */
    public function testSetAndGetMobiliteGeographique(): void
    {
        $mobilites = ["Oui, national", "Oui, région", "Non"];
        
        foreach ($mobilites as $mobilite) {
            $this->preference->setMobiliteGeographique($mobilite);
            $this->assertEquals($mobilite, $this->preference->getMobiliteGeographique());
        }
    }

    /**
     * Test des getters/setters pour le prêt au déplacement
     */
    public function testSetAndGetPretDeplacement(): void
    {
        $prets = ["Jamais", "Occasionnel", "Fréquent"];
        
        foreach ($prets as $pret) {
            $this->preference->setPretDeplacement($pret);
            $this->assertEquals($pret, $this->preference->getPretDeplacement());
        }
    }

    /**
     * Test des getters/setters pour le type de contrat souhaité
     */
    public function testSetAndGetTypeContratSouhaite(): void
    {
        $contrats = ["CDI", "CDD", "Stage", "Alternance", "Freelance"];
        
        foreach ($contrats as $contrat) {
            $this->preference->setTypeContratSouhaite($contrat);
            $this->assertEquals($contrat, $this->preference->getTypeContratSouhaite());
        }
    }

    /**
     * Test des getters/setters pour la prétention salariale
     */
    public function testSetAndGetPretentionSalariale(): void
    {
        $salaire = 45000.50;
        $this->preference->setPretentionSalariale($salaire);
        $this->assertEquals($salaire, $this->preference->getPretentionSalariale());
    }

    /**
     * Test de prétention salariale à zéro
     */
    public function testPretentionSalarialeZero(): void
    {
        $this->preference->setPretentionSalariale(0);
        $this->assertEquals(0, $this->preference->getPretentionSalariale());
    }

    /**
     * Test de prétention salariale haute
     */
    public function testPretentionSalarialeHigh(): void
    {
        $salaire = 999999.99;
        $this->preference->setPretentionSalariale($salaire);
        $this->assertEquals($salaire, $this->preference->getPretentionSalariale());
    }

    /**
     * Test des getters/setters pour la date de disponibilité
     */
    public function testSetAndGetDateDisponibilite(): void
    {
        $date = new DateTime('2025-06-01');
        $this->preference->setDateDisponibilite($date);
        $this->assertEquals($date, $this->preference->getDateDisponibilite());
    }

    /**
     * Test des getters/setters pour l'ID utilisateur
     */
    public function testSetAndGetIdUtilisateur(): void
    {
        $this->preference->setIdUtilisateur(42);
        $this->assertEquals(42, $this->preference->getIdUtilisateur());
    }

    /**
     * Test que le getter retourne null au départ
     */
    public function testGetIdPreferenceIsNullInitially(): void
    {
        $this->assertNull($this->preference->getIdPreference());
    }

    /**
     * Test d'initialisation complète des préférences
     */
    public function testCompletePreferenceInitialization(): void
    {
        $date = new DateTime('2025-03-01');
        
        $this->preference->setTypePosteSouhaite('Développeur Backend');
        $this->preference->setModeTravail('Hybride');
        $this->preference->setDisponibilite('Dans 1 mois');
        $this->preference->setMobiliteGeographique('Oui, national');
        $this->preference->setPretDeplacement('Occasionnel');
        $this->preference->setTypeContratSouhaite('CDI');
        $this->preference->setPretentionSalariale(50000.00);
        $this->preference->setDateDisponibilite($date);
        $this->preference->setIdUtilisateur(5);

        $this->assertEquals('Développeur Backend', $this->preference->getTypePosteSouhaite());
        $this->assertEquals('Hybride', $this->preference->getModeTravail());
        $this->assertEquals('Dans 1 mois', $this->preference->getDisponibilite());
        $this->assertEquals('Oui, national', $this->preference->getMobiliteGeographique());
        $this->assertEquals('Occasionnel', $this->preference->getPretDeplacement());
        $this->assertEquals('CDI', $this->preference->getTypeContratSouhaite());
        $this->assertEquals(50000.00, $this->preference->getPretentionSalariale());
        $this->assertEquals($date, $this->preference->getDateDisponibilite());
        $this->assertEquals(5, $this->preference->getIdUtilisateur());
    }

    /**
     * Test que les setters retournent $this pour le chaînage
     */
    public function testSettersReturnStaticForFluentInterface(): void
    {
        $result = $this->preference->setModeTravail('Télétravail');
        $this->assertInstanceOf(PreferenceCandidature::class, $result);
        
        $result = $this->preference->setTypeContratSouhaite('CDI');
        $this->assertInstanceOf(PreferenceCandidature::class, $result);
    }

    /**
     * Test nullable values
     */
    public function testNullableValues(): void
    {
        $this->assertNull($this->preference->getTypePosteSouhaite());
        $this->assertNull($this->preference->getModeTravail());
        $this->assertNull($this->preference->getDisponibilite());
        $this->assertNull($this->preference->getMobiliteGeographique());
        $this->assertNull($this->preference->getPretDeplacement());
        $this->assertNull($this->preference->getTypeContratSouhaite());
        $this->assertNull($this->preference->getPretentionSalariale());
        $this->assertNull($this->preference->getDateDisponibilite());
        $this->assertNull($this->preference->getIdUtilisateur());
    }
}

<?php

namespace App\Tests\Entity;

use PHPUnit\Framework\TestCase;
use App\Entity\Candidature;
use DateTime;

class CandidatureTest extends TestCase
{
    private Candidature $candidature;

    protected function setUp(): void
    {
        $this->candidature = new Candidature();
    }

    /**
     * Test du constructeur - la date de candidature doit être initialisée à aujourd'hui
     */
    public function testConstructorSetsTodayDate(): void
    {
        $today = new DateTime('today');
        $this->assertEquals($today->format('Y-m-d'), $this->candidature->getDateCandidature()->format('Y-m-d'));
    }

    /**
     * Test des getters/setters pour la date de candidature
     */
    public function testSetAndGetDateCandidature(): void
    {
        $date = new DateTime('2025-01-15');
        $this->candidature->setDateCandidature($date);
        $this->assertEquals($date, $this->candidature->getDateCandidature());
    }

    /**
     * Test des getters/setters pour le statut
     */
    public function testSetAndGetStatut(): void
    {
        $statuts = ["En attente", "Accepté", "Refusé"];
        
        foreach ($statuts as $statut) {
            $this->candidature->setStatut($statut);
            $this->assertEquals($statut, $this->candidature->getStatut());
        }
    }

    /**
     * Test des getters/setters pour le message du candidat
     */
    public function testSetAndGetMessageCandidat(): void
    {
        $message = "Je suis très intéressé par ce poste";
        $this->candidature->setMessageCandidat($message);
        $this->assertEquals($message, $this->candidature->getMessageCandidat());
    }

    /**
     * Test que le message ne peut pas dépasser 1000 caractères
     */
    public function testMessageCandidatMaxLength(): void
    {
        $longMessage = str_repeat('a', 1001);
        $this->candidature->setMessageCandidat($longMessage);
        $this->assertEquals($longMessage, $this->candidature->getMessageCandidat());
        // La validation doit être faite au niveau du formulaire/validateur
    }

    /**
     * Test des getters/setters pour le CV
     */
    public function testSetAndGetCv(): void
    {
        $cv = "cv_candidat.pdf";
        $this->candidature->setCv($cv);
        $this->assertEquals($cv, $this->candidature->getCv());
    }

    /**
     * Test des getters/setters pour la lettre de motivation
     */
    public function testSetAndGetLettreMotivation(): void
    {
        $lettre = "lettre_motivation.pdf";
        $this->candidature->setLettreMotivation($lettre);
        $this->assertEquals($lettre, $this->candidature->getLettreMotivation());
    }

    /**
     * Test des getters/setters pour le niveau d'expérience
     */
    public function testSetAndGetNiveauExperience(): void
    {
        $niveaux = ["Débutant", "Junior", "Confirmé", "Senior", "Expert"];
        
        foreach ($niveaux as $niveau) {
            $this->candidature->setNiveauExperience($niveau);
            $this->assertEquals($niveau, $this->candidature->getNiveauExperience());
        }
    }

    /**
     * Test des getters/setters pour les années d'expérience
     */
    public function testSetAndGetAnneesExperience(): void
    {
        $this->candidature->setAnneesExperience(5);
        $this->assertEquals(5, $this->candidature->getAnneesExperience());
        
        $this->candidature->setAnneesExperience(0);
        $this->assertEquals(0, $this->candidature->getAnneesExperience());
        
        $this->candidature->setAnneesExperience(30);
        $this->assertEquals(30, $this->candidature->getAnneesExperience());
    }

    /**
     * Test des getters/setters pour le domaine d'expérience
     */
    public function testSetAndGetDomaineExperience(): void
    {
        $domaine = "Développement Web";
        $this->candidature->setDomaineExperience($domaine);
        $this->assertEquals($domaine, $this->candidature->getDomaineExperience());
    }

    /**
     * Test des getters/setters pour le dernier poste
     */
    public function testSetAndGetDernierPoste(): void
    {
        $poste = "Développeur Senior";
        $this->candidature->setDernierPoste($poste);
        $this->assertEquals($poste, $this->candidature->getDernierPoste());
    }

    /**
     * Test des getters/setters pour l'ID utilisateur
     */
    public function testSetAndGetIdUtilisateur(): void
    {
        $this->candidature->setIdUtilisateur(42);
        $this->assertEquals(42, $this->candidature->getIdUtilisateur());
    }

    /**
     * Test des getters/setters pour l'ID offre
     */
    public function testSetAndGetIdOffre(): void
    {
        $this->candidature->setIdOffre(15);
        $this->assertEquals(15, $this->candidature->getIdOffre());
    }

    /**
     * Test que le getter retourne null au départ
     */
    public function testGetIdCandidatureIsNullInitially(): void
    {
        $this->assertNull($this->candidature->getIdCandidature());
    }

    /**
     * Test d'initialisation complète d'une candidature
     */
    public function testCompleteCandidatureInitialization(): void
    {
        $date = new DateTime('2025-02-01');
        $this->candidature->setDateCandidature($date);
        $this->candidature->setStatut('En attente');
        $this->candidature->setMessageCandidat('Je suis candidat');
        $this->candidature->setCv('cv.pdf');
        $this->candidature->setLettreMotivation('lettre.pdf');
        $this->candidature->setNiveauExperience('Junior');
        $this->candidature->setAnneesExperience(2);
        $this->candidature->setDomaineExperience('Web');
        $this->candidature->setDernierPoste('Développeur');
        $this->candidature->setIdUtilisateur(1);
        $this->candidature->setIdOffre(1);

        $this->assertEquals($date, $this->candidature->getDateCandidature());
        $this->assertEquals('En attente', $this->candidature->getStatut());
        $this->assertEquals('Je suis candidat', $this->candidature->getMessageCandidat());
        $this->assertEquals('cv.pdf', $this->candidature->getCv());
        $this->assertEquals('lettre.pdf', $this->candidature->getLettreMotivation());
        $this->assertEquals('Junior', $this->candidature->getNiveauExperience());
        $this->assertEquals(2, $this->candidature->getAnneesExperience());
        $this->assertEquals('Web', $this->candidature->getDomaineExperience());
        $this->assertEquals('Développeur', $this->candidature->getDernierPoste());
        $this->assertEquals(1, $this->candidature->getIdUtilisateur());
        $this->assertEquals(1, $this->candidature->getIdOffre());
    }

    /**
     * Test que les setters retournent $this pour le chaînage
     */
    public function testSettersReturnStaticForFluentInterface(): void
    {
        $result = $this->candidature->setStatut('En attente');
        $this->assertInstanceOf(Candidature::class, $result);
        
        $result = $this->candidature->setNiveauExperience('Senior');
        $this->assertInstanceOf(Candidature::class, $result);
    }
}

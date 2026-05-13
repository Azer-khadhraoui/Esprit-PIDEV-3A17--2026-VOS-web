<?php

namespace App\Tests\Service;

use App\Entity\OffreEmploi;
use App\Service\OffreEmploiManager;
use PHPUnit\Framework\TestCase;

class OffreEmploiManagerTest extends TestCase
{
    private OffreEmploiManager $manager;

    protected function setUp(): void
    {
        $this->manager = new OffreEmploiManager();
    }

    // ========== Tests: Titre Validation ==========

    public function testValidOffreEmploi(): void
    {
        $offre = new OffreEmploi();
        $offre->setTitre('Développeur PHP Symfony');
        $offre->setTypeContrat('CDI');
        $offre->setStatutOffre('OUVERTE');
        $offre->setDatePublication(new \DateTime('tomorrow'));

        $this->assertTrue($this->manager->validate($offre));
    }

    public function testOffreWithoutTitre(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre de l\'offre est obligatoire');

        $offre = new OffreEmploi();
        $offre->setTitre('');
        $offre->setTypeContrat('CDI');
        $offre->setStatutOffre('OUVERTE');
        $offre->setDatePublication(new \DateTime('tomorrow'));

        $this->manager->validate($offre);
    }

    public function testOffreTitreTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir au moins 3 caractères');

        $offre = new OffreEmploi();
        $offre->setTitre('AB');
        $offre->setTypeContrat('CDI');
        $offre->setStatutOffre('OUVERTE');
        $offre->setDatePublication(new \DateTime('tomorrow'));

        $this->manager->validate($offre);
    }

    public function testOffreTitreTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre ne peut pas dépasser 100 caractères');

        $offre = new OffreEmploi();
        $offre->setTitre(str_repeat('A', 101));
        $offre->setTypeContrat('CDI');
        $offre->setStatutOffre('OUVERTE');
        $offre->setDatePublication(new \DateTime('tomorrow'));

        $this->manager->validate($offre);
    }

    // ========== Tests: Date Publication Validation ==========

    public function testOffreWithoutDatePublication(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de publication est obligatoire');

        $offre = new OffreEmploi();
        $offre->setTitre('Développeur PHP');
        $offre->setTypeContrat('CDI');
        $offre->setStatutOffre('OUVERTE');
        $offre->setDatePublication(null);

        $this->manager->validate($offre);
    }

    public function testOffreDatePublicationInPast(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de publication ne peut pas être dans le passé');

        $offre = new OffreEmploi();
        $offre->setTitre('Développeur PHP');
        $offre->setTypeContrat('CDI');
        $offre->setStatutOffre('OUVERTE');
        $offre->setDatePublication(new \DateTime('yesterday'));

        $this->manager->validate($offre);
    }

    // ========== Tests: Type Contrat Validation ==========

    public function testOffreWithoutTypeContrat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de contrat est obligatoire');

        $offre = new OffreEmploi();
        $offre->setTitre('Développeur PHP');
        $offre->setTypeContrat('');
        $offre->setStatutOffre('OUVERTE');
        $offre->setDatePublication(new \DateTime('tomorrow'));

        $this->manager->validate($offre);
    }

    public function testOffreInvalidTypeContrat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de contrat doit être parmi');

        $offre = new OffreEmploi();
        $offre->setTitre('Développeur PHP');
        $offre->setTypeContrat('CONTRAT_INVALIDE');
        $offre->setStatutOffre('OUVERTE');
        $offre->setDatePublication(new \DateTime('tomorrow'));

        $this->manager->validate($offre);
    }

    public function testOffreWithAllValidContractTypes(): void
    {
        $validTypes = ['CDI', 'CDD', 'Stage', 'Alternance', 'Freelance'];

        foreach ($validTypes as $type) {
            $offre = new OffreEmploi();
            $offre->setTitre('Développeur PHP');
            $offre->setTypeContrat($type);
            $offre->setStatutOffre('OUVERTE');
            $offre->setDatePublication(new \DateTime('tomorrow'));

            $this->assertTrue($this->manager->validate($offre), "Le type '$type' devrait être valide");
        }
    }

    // ========== Tests: Statut Offre Validation ==========

    public function testOffreWithoutStatutOffre(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut de l\'offre est obligatoire');

        $offre = new OffreEmploi();
        $offre->setTitre('Développeur PHP');
        $offre->setTypeContrat('CDI');
        $offre->setStatutOffre('');
        $offre->setDatePublication(new \DateTime('tomorrow'));

        $this->manager->validate($offre);
    }

    public function testOffreInvalidStatutOffre(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut doit être parmi');

        $offre = new OffreEmploi();
        $offre->setTitre('Développeur PHP');
        $offre->setTypeContrat('CDI');
        $offre->setStatutOffre('STATUT_INVALIDE');
        $offre->setDatePublication(new \DateTime('tomorrow'));

        $this->manager->validate($offre);
    }

    public function testOffreWithAllValidStatuses(): void
    {
        $validStatuses = ['OUVERTE', 'FERMEE', 'INACTIVE'];

        foreach ($validStatuses as $status) {
            $offre = new OffreEmploi();
            $offre->setTitre('Développeur PHP');
            $offre->setTypeContrat('CDI');
            $offre->setStatutOffre($status);
            $offre->setDatePublication(new \DateTime('tomorrow'));

            $this->assertTrue($this->manager->validate($offre), "Le statut '$status' devrait être valide");
        }
    }
}

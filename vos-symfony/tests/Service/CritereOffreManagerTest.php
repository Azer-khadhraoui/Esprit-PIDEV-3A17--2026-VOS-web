<?php

namespace App\Tests\Service;

use App\Entity\CritereOffre;
use App\Service\CritereOffreManager;
use PHPUnit\Framework\TestCase;

class CritereOffreManagerTest extends TestCase
{
    private CritereOffreManager $manager;

    protected function setUp(): void
    {
        $this->manager = new CritereOffreManager();
    }

    // ========== Tests: Niveau Experience Validation ==========

    public function testValidCritereOffre(): void
    {
        $critere = new CritereOffre();
        $critere->setNiveauExperience('Senior');
        $critere->setNiveauEtude('Bac+5');
        $critere->setCompetencesRequises('PHP, Symfony, MySQL, Docker');
        $critere->setResponsibilities('Développer et maintenir des applications web');

        $this->assertTrue($this->manager->validate($critere));
    }

    public function testCritereWithoutNiveauExperience(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le niveau d\'expérience est obligatoire');

        $critere = new CritereOffre();
        $critere->setNiveauExperience('');
        $critere->setNiveauEtude('Bac+5');
        $critere->setCompetencesRequises('PHP, Symfony, MySQL');
        $critere->setResponsibilities('Développer des applications web');

        $this->manager->validate($critere);
    }

    public function testCritereNiveauExperienceTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le niveau d\'expérience doit contenir au moins 2 caractères');

        $critere = new CritereOffre();
        $critere->setNiveauExperience('A');
        $critere->setNiveauEtude('Bac+5');
        $critere->setCompetencesRequises('PHP, Symfony, MySQL');
        $critere->setResponsibilities('Développer des applications web');

        $this->manager->validate($critere);
    }

    public function testCritereNiveauExperienceTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le niveau d\'expérience ne peut pas dépasser 50 caractères');

        $critere = new CritereOffre();
        $critere->setNiveauExperience(str_repeat('A', 51));
        $critere->setNiveauEtude('Bac+5');
        $critere->setCompetencesRequises('PHP, Symfony, MySQL');
        $critere->setResponsibilities('Développer des applications web');

        $this->manager->validate($critere);
    }

    // ========== Tests: Niveau Etude Validation ==========

    public function testCritereWithoutNiveauEtude(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le niveau d\'étude est obligatoire');

        $critere = new CritereOffre();
        $critere->setNiveauExperience('Senior');
        $critere->setNiveauEtude('');
        $critere->setCompetencesRequises('PHP, Symfony, MySQL');
        $critere->setResponsibilities('Développer des applications web');

        $this->manager->validate($critere);
    }

    public function testCritereNiveauEtudeTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le niveau d\'étude doit contenir au moins 2 caractères');

        $critere = new CritereOffre();
        $critere->setNiveauExperience('Senior');
        $critere->setNiveauEtude('B');
        $critere->setCompetencesRequises('PHP, Symfony, MySQL');
        $critere->setResponsibilities('Développer des applications web');

        $this->manager->validate($critere);
    }

    public function testCritereNiveauEtudeTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le niveau d\'étude ne peut pas dépasser 50 caractères');

        $critere = new CritereOffre();
        $critere->setNiveauExperience('Senior');
        $critere->setNiveauEtude(str_repeat('B', 51));
        $critere->setCompetencesRequises('PHP, Symfony, MySQL');
        $critere->setResponsibilities('Développer des applications web');

        $this->manager->validate($critere);
    }

    // ========== Tests: Competences Requises Validation ==========

    public function testCritereWithoutCompetencesRequises(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les compétences requises sont obligatoires');

        $critere = new CritereOffre();
        $critere->setNiveauExperience('Senior');
        $critere->setNiveauEtude('Bac+5');
        $critere->setCompetencesRequises('');
        $critere->setResponsibilities('Développer des applications web');

        $this->manager->validate($critere);
    }

    public function testCritereCompetencesRequisesTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les compétences requises doivent contenir au moins 3 caractères');

        $critere = new CritereOffre();
        $critere->setNiveauExperience('Senior');
        $critere->setNiveauEtude('Bac+5');
        $critere->setCompetencesRequises('AB');
        $critere->setResponsibilities('Développer des applications web');

        $this->manager->validate($critere);
    }

    public function testCritereCompetencesRequisesTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les compétences requises ne peuvent pas dépasser 2000 caractères');

        $critere = new CritereOffre();
        $critere->setNiveauExperience('Senior');
        $critere->setNiveauEtude('Bac+5');
        $critere->setCompetencesRequises(str_repeat('A', 2001));
        $critere->setResponsibilities('Développer des applications web');

        $this->manager->validate($critere);
    }

    // ========== Tests: Responsibilities Validation ==========

    public function testCritereWithoutResponsibilities(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les responsabilités sont obligatoires');

        $critere = new CritereOffre();
        $critere->setNiveauExperience('Senior');
        $critere->setNiveauEtude('Bac+5');
        $critere->setCompetencesRequises('PHP, Symfony, MySQL');
        $critere->setResponsibilities('');

        $this->manager->validate($critere);
    }

    public function testCritereResponsibilitiesTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les responsabilités doivent contenir au moins 3 caractères');

        $critere = new CritereOffre();
        $critere->setNiveauExperience('Senior');
        $critere->setNiveauEtude('Bac+5');
        $critere->setCompetencesRequises('PHP, Symfony, MySQL');
        $critere->setResponsibilities('AB');

        $this->manager->validate($critere);
    }

    public function testCritereResponsibilitiesTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les responsabilités ne peuvent pas dépasser 2000 caractères');

        $critere = new CritereOffre();
        $critere->setNiveauExperience('Senior');
        $critere->setNiveauEtude('Bac+5');
        $critere->setCompetencesRequises('PHP, Symfony, MySQL');
        $critere->setResponsibilities(str_repeat('A', 2001));

        $this->manager->validate($critere);
    }

    // ========== Tests: Boundary Values ==========

    public function testCritereLimitesBoundaryMin(): void
    {
        $critere = new CritereOffre();
        $critere->setNiveauExperience('AB');  // exactly 2 chars
        $critere->setNiveauEtude('CD');      // exactly 2 chars
        $critere->setCompetencesRequises('ABC'); // exactly 3 chars
        $critere->setResponsibilities('DEF');     // exactly 3 chars

        $this->assertTrue($this->manager->validate($critere));
    }

    public function testCritereLimitesBoundaryMax(): void
    {
        $critere = new CritereOffre();
        $critere->setNiveauExperience(str_repeat('A', 50));     // exactly 50 chars
        $critere->setNiveauEtude(str_repeat('B', 50));         // exactly 50 chars
        $critere->setCompetencesRequises(str_repeat('C', 2000)); // exactly 2000 chars
        $critere->setResponsibilities(str_repeat('D', 2000));     // exactly 2000 chars

        $this->assertTrue($this->manager->validate($critere));
    }
}

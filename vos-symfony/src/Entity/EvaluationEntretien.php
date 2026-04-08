<?php

namespace App\Entity;

use App\Repository\EvaluationEntretienRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvaluationEntretienRepository::class)]
#[ORM\Table(name: 'evaluation_entretien')]
class EvaluationEntretien
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_evaluation', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'score_test', type: 'float', nullable: true)]
    private ?float $scoreTest = null;

    #[ORM\Column(name: 'note_entretien', nullable: true)]
    private ?int $noteEntretien = null;

    #[ORM\Column(name: 'commentaire', type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(name: 'decision', length: 50, nullable: true)]
    private ?string $decision = null;

    #[ORM\Column(name: 'competences_techniques', nullable: true)]
    private ?int $competencesTechniques = null;

    #[ORM\Column(name: 'competences_comportementales', nullable: true)]
    private ?int $competencesComportementales = null;

    #[ORM\Column(name: 'communication', nullable: true)]
    private ?int $communication = null;

    #[ORM\Column(name: 'motivation', nullable: true)]
    private ?int $motivation = null;

    #[ORM\Column(name: 'experience', nullable: true)]
    private ?int $experience = null;

    #[ORM\ManyToOne(targetEntity: Entretien::class, inversedBy: 'evaluationEntretiens')]
    #[ORM\JoinColumn(name: 'id_entretien', referencedColumnName: 'id_entretien')]
    private ?Entretien $entretien = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScoreTest(): ?float
    {
        return $this->scoreTest;
    }

    public function setScoreTest(?float $scoreTest): static
    {
        $this->scoreTest = $scoreTest;
        return $this;
    }

    public function getNoteEntretien(): ?int
    {
        return $this->noteEntretien;
    }

    public function setNoteEntretien(?int $noteEntretien): static
    {
        $this->noteEntretien = $noteEntretien;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getDecision(): ?string
    {
        return $this->decision;
    }

    public function setDecision(?string $decision): static
    {
        $this->decision = $decision;
        return $this;
    }

    public function getCompetencesTechniques(): ?int
    {
        return $this->competencesTechniques;
    }

    public function setCompetencesTechniques(?int $competencesTechniques): static
    {
        $this->competencesTechniques = $competencesTechniques;
        return $this;
    }

    public function getCompetencesComportementales(): ?int
    {
        return $this->competencesComportementales;
    }

    public function setCompetencesComportementales(?int $competencesComportementales): static
    {
        $this->competencesComportementales = $competencesComportementales;
        return $this;
    }

    public function getCommunication(): ?int
    {
        return $this->communication;
    }

    public function setCommunication(?int $communication): static
    {
        $this->communication = $communication;
        return $this;
    }

    public function getMotivation(): ?int
    {
        return $this->motivation;
    }

    public function setMotivation(?int $motivation): static
    {
        $this->motivation = $motivation;
        return $this;
    }

    public function getExperience(): ?int
    {
        return $this->experience;
    }

    public function setExperience(?int $experience): static
    {
        $this->experience = $experience;
        return $this;
    }

    public function getEntretien(): ?Entretien
    {
        return $this->entretien;
    }

    public function setEntretien(?Entretien $entretien): static
    {
        $this->entretien = $entretien;
        return $this;
    }
}

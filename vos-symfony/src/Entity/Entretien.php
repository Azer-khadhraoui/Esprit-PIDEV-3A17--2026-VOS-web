<?php

namespace App\Entity;

use App\Repository\EntretienRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntretienRepository::class)]
#[ORM\Table(name: 'entretien')]
class Entretien
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_entretien', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_entretien', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateEntretien = null;

    #[ORM\Column(name: 'heure_entretien', type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heureEntretien = null;

    #[ORM\Column(name: 'type_entretien', length: 50, nullable: true)]
    private ?string $typeEntretien = null;

    #[ORM\Column(name: 'statut_entretien', length: 100, nullable: true)]
    private ?string $statutEntretien = null;

    #[ORM\Column(name: 'lieu', length: 100, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(name: 'type_test', length: 100, nullable: true)]
    private ?string $typeTest = null;

    #[ORM\Column(name: 'questions_entretien', type: Types::TEXT, nullable: true)]
    private ?string $questionsEntretien = null;

    #[ORM\Column(name: 'lien_reunion', length: 500, nullable: true)]
    private ?string $lienReunion = null;

    #[ORM\Column(name: 'id_candidature', nullable: true)]
    private ?int $idCandidature = null;

    #[ORM\Column(name: 'id_utilisateur', nullable: true)]
    private ?int $idUtilisateur = null;

    #[ORM\Column(name: 'calendar_event_id', length: 255, nullable: true)]
    private ?string $calendarEventId = null;

    /**
     * @var Collection<int, EvaluationEntretien>
     */
    #[ORM\OneToMany(targetEntity: EvaluationEntretien::class, mappedBy: 'entretien')]
    private Collection $evaluationEntretiens;

    public function __construct()
    {
        $this->evaluationEntretiens = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateEntretien(): ?\DateTime
    {
        return $this->dateEntretien;
    }

    public function setDateEntretien(?\DateTime $dateEntretien): static
    {
        $this->dateEntretien = $dateEntretien;
        return $this;
    }

    public function getHeureEntretien(): ?\DateTime
    {
        return $this->heureEntretien;
    }

    public function setHeureEntretien(?\DateTime $heureEntretien): static
    {
        $this->heureEntretien = $heureEntretien;
        return $this;
    }

    public function getTypeEntretien(): ?string
    {
        return $this->typeEntretien;
    }

    public function setTypeEntretien(?string $typeEntretien): static
    {
        $this->typeEntretien = $typeEntretien;
        return $this;
    }

    public function getStatutEntretien(): ?string
    {
        return $this->statutEntretien;
    }

    public function setStatutEntretien(?string $statutEntretien): static
    {
        $this->statutEntretien = $statutEntretien;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getTypeTest(): ?string
    {
        return $this->typeTest;
    }

    public function setTypeTest(?string $typeTest): static
    {
        $this->typeTest = $typeTest;
        return $this;
    }

    public function getQuestionsEntretien(): ?string
    {
        return $this->questionsEntretien;
    }

    public function setQuestionsEntretien(?string $questionsEntretien): static
    {
        $this->questionsEntretien = $questionsEntretien;
        return $this;
    }

    public function getLienReunion(): ?string
    {
        return $this->lienReunion;
    }

    public function setLienReunion(?string $lienReunion): static
    {
        $this->lienReunion = $lienReunion;
        return $this;
    }

    public function getIdCandidature(): ?int
    {
        return $this->idCandidature;
    }

    public function setIdCandidature(?int $idCandidature): static
    {
        $this->idCandidature = $idCandidature;
        return $this;
    }

    public function getIdUtilisateur(): ?int
    {
        return $this->idUtilisateur;
    }

    public function setIdUtilisateur(?int $idUtilisateur): static
    {
        $this->idUtilisateur = $idUtilisateur;
        return $this;
    }

    public function getCalendarEventId(): ?string
    {
        return $this->calendarEventId;
    }

    public function setCalendarEventId(?string $calendarEventId): static
    {
        $this->calendarEventId = $calendarEventId;
        return $this;
    }

    /**
     * @return Collection<int, EvaluationEntretien>
     */
    public function getEvaluationEntretiens(): Collection
    {
        return $this->evaluationEntretiens;
    }

    public function addEvaluationEntretien(EvaluationEntretien $evaluationEntretien): static
    {
        if (!$this->evaluationEntretiens->contains($evaluationEntretien)) {
            $this->evaluationEntretiens->add($evaluationEntretien);
            $evaluationEntretien->setEntretien($this);
        }

        return $this;
    }

    public function removeEvaluationEntretien(EvaluationEntretien $evaluationEntretien): static
    {
        if ($this->evaluationEntretiens->removeElement($evaluationEntretien) && $evaluationEntretien->getEntretien() === $this) {
            $evaluationEntretien->setEntretien(null);
        }

        return $this;
    }
}

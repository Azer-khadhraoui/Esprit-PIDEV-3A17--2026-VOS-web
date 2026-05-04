<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'recrutement')]
class Recrutement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_recrutement', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_decision', type: 'date', nullable: true)]
    #[Assert\NotNull(message: 'La date de decision est requise.')]
    #[Assert\GreaterThanOrEqual(value: 'today', message: 'La date de decision ne peut pas etre dans le passe.')]
    private ?\DateTimeInterface $dateDecision = null;

    #[ORM\Column(name: 'decision_finale', type: 'string', length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'La decision finale est requise.')]
    private ?string $decisionFinale = null;

    #[ORM\Column(name: 'id_entretien', type: 'integer', nullable: true)]
    #[Assert\Positive(message: "L'identifiant de l'entretien doit etre un nombre positif.")]
    private ?int $idEntretien = null;

    #[ORM\Column(name: 'id_utilisateur', type: 'integer', nullable: true)]
    #[Assert\Positive(message: "L'identifiant de l'utilisateur doit etre un nombre positif.")]
    private ?int $idUtilisateur = null;

    #[ORM\Column(name: 'calendar_event_id', type: 'string', length: 255, nullable: true)]
    private ?string $calendarEventId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateDecision(): ?\DateTimeInterface
    {
        return $this->dateDecision;
    }

    public function setDateDecision(?\DateTimeInterface $dateDecision): self
    {
        $this->dateDecision = $dateDecision;

        return $this;
    }

    public function getDecisionFinale(): ?string
    {
        return $this->decisionFinale;
    }

    public function setDecisionFinale(?string $decisionFinale): self
    {
        $this->decisionFinale = $decisionFinale;

        return $this;
    }

    public function getIdEntretien(): ?int
    {
        return $this->idEntretien;
    }

    public function setIdEntretien(?int $idEntretien): self
    {
        $this->idEntretien = $idEntretien;

        return $this;
    }

    public function getIdUtilisateur(): ?int
    {
        return $this->idUtilisateur;
    }

    public function setIdUtilisateur(?int $idUtilisateur): self
    {
        $this->idUtilisateur = $idUtilisateur;

        return $this;
    }

    public function getCalendarEventId(): ?string
    {
        return $this->calendarEventId;
    }

    public function setCalendarEventId(?string $calendarEventId): self
    {
        $this->calendarEventId = $calendarEventId;

        return $this;
    }
}

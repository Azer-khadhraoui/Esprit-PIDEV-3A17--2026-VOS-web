<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'contrat_embauche')]
class ContratEmbauche
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_contrat', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'type_contrat', type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Le type de contrat est requis.')]
    private ?string $typeContrat = null;

    #[ORM\Column(name: 'date_debut', type: 'date')]
    #[Assert\NotNull(message: 'La date de début est requise.')]
    #[Assert\GreaterThanOrEqual('today', message: 'La date de début ne peut pas être antérieure à aujourd\'hui.')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'date_fin', type: 'date')]
    #[Assert\NotNull(message: 'La date de fin est requise.')]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(name: 'salaire', type: 'float')]
    #[Assert\NotBlank(message: 'Le salaire est requis.')]
    #[Assert\Positive(message: 'Le salaire doit être un nombre positif.')]
    private ?float $salaire = null;

    #[ORM\Column(name: 'status', type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Le statut est requis.')]
    private ?string $status = null;

    #[ORM\Column(name: 'volume_horaire', type: 'string', length: 20)]
    #[Assert\NotBlank(message: 'Le volume horaire est requis.')]
    private ?string $volumeHoraire = null;

    #[ORM\Column(name: 'avantages', type: 'text', nullable: true)]
    private ?string $avantages = null;

    #[ORM\Column(name: 'id_recrutement', type: 'integer')]
    #[Assert\NotBlank(message: "L'ID du recrutement est requis.")]
    #[Assert\Positive(message: "L'ID du recrutement doit être positif.")]
    private ?int $idRecrutement = null;

    #[ORM\Column(name: 'periode', type: 'string', length: 50, nullable: true)]
    private ?string $periode = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeContrat(): ?string
    {
        return $this->typeContrat;
    }

    public function setTypeContrat(string $typeContrat): self
    {
        $this->typeContrat = $typeContrat;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): self
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getSalaire(): ?float
    {
        return $this->salaire;
    }

    public function setSalaire(float $salaire): self
    {
        $this->salaire = $salaire;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getVolumeHoraire(): ?string
    {
        return $this->volumeHoraire;
    }

    public function setVolumeHoraire(string $volumeHoraire): self
    {
        $this->volumeHoraire = $volumeHoraire;

        return $this;
    }

    public function getAvantages(): ?string
    {
        return $this->avantages;
    }

    public function setAvantages(?string $avantages): self
    {
        $this->avantages = $avantages;

        return $this;
    }

    public function getIdRecrutement(): ?int
    {
        return $this->idRecrutement;
    }

    public function setIdRecrutement(int $idRecrutement): self
    {
        $this->idRecrutement = $idRecrutement;

        return $this;
    }

    public function getPeriode(): ?string
    {
        if ($this->dateDebut && $this->dateFin) {
            $interval = $this->dateDebut->diff($this->dateFin);
            return $interval->format('%a jours');
        }
        return null;
    }

    public function setPeriode(?string $periode): self
    {
        $this->periode = $periode;

        return $this;
    }
}

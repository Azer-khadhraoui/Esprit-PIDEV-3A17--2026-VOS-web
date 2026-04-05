<?php

namespace App\Entity;

use App\Repository\PreferenceCandidatureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: PreferenceCandidatureRepository::class)]
class PreferenceCandidature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id_preference = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: "Le type de poste souhaité est obligatoire.")]
    #[Assert\Length(max: 100, maxMessage: "100 caractères maximum.")]
    private ?string $type_poste_souhaite = null;

    #[ORM\Column(length: 50, nullable: true)]
     #[Assert\Choice(
        choices: ["100% Présentiel", "100% Télétravail", "Hybride"],
        message: "Mode de travail invalide."
    )]
    private ?string $mode_travail = null;

    #[ORM\Column(length: 50, nullable: true)]
       #[Assert\Choice(
        choices: ["Immédiatement", "Dans 1 mois", "Dans 3 mois", "Dans 6 mois"],
        message: "Disponibilité invalide."
    )]
    private ?string $disponibilite = null;

    #[ORM\Column(length: 50, nullable: true)]
     #[Assert\Choice(
        choices: ["Oui, national", "Oui, région", "Non"],
        message: "Mobilité géographique invalide."
    )]
    private ?string $mobilite_geographique = null;

    #[ORM\Column(length: 50, nullable: true)]
     #[Assert\Choice(
        choices: ["Jamais", "Occasionnel", "Fréquent"],
        message: "Valeur invalide pour prêt au déplacement."
    )]
    private ?string $pret_deplacement = null;

    #[ORM\Column(length: 50, nullable: true)]
     #[Assert\Choice(
        choices: ["CDI", "CDD", "Stage", "Alternance", "Freelance"],
        message: "Type de contrat invalide."
    )]
    private ?string $type_contrat_souhaite = null;

    #[ORM\Column(nullable: true)]
     #[Assert\PositiveOrZero(message: "La prétention salariale doit être >= 0.")]
    #[Assert\LessThanOrEqual(100000, message: "Valeur salariale trop élevée.")]
    private ?float $pretention_salariale = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual("today", message: "La date de disponibilité doit être dans le futur.")]
    private ?\DateTime $date_disponibilite = null;

    #[ORM\Column(nullable: true)]
    private ?int $id_utilisateur = null;

    public function getIdPreference(): ?int
    {
        return $this->id_preference;
    }

    public function getTypePosteSouhaite(): ?string
    {
        return $this->type_poste_souhaite;
    }

    public function setTypePosteSouhaite(?string $type_poste_souhaite): static
    {
        $this->type_poste_souhaite = $type_poste_souhaite;

        return $this;
    }

    public function getModeTravail(): ?string
    {
        return $this->mode_travail;
    }

    public function setModeTravail(?string $mode_travail): static
    {
        $this->mode_travail = $mode_travail;

        return $this;
    }

    public function getDisponibilite(): ?string
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(?string $disponibilite): static
    {
        $this->disponibilite = $disponibilite;

        return $this;
    }

    public function getMobiliteGeographique(): ?string
    {
        return $this->mobilite_geographique;
    }

    public function setMobiliteGeographique(?string $mobilite_geographique): static
    {
        $this->mobilite_geographique = $mobilite_geographique;

        return $this;
    }

    public function getPretDeplacement(): ?string
    {
        return $this->pret_deplacement;
    }

    public function setPretDeplacement(?string $pret_deplacement): static
    {
        $this->pret_deplacement = $pret_deplacement;

        return $this;
    }

    public function getTypeContratSouhaite(): ?string
    {
        return $this->type_contrat_souhaite;
    }

    public function setTypeContratSouhaite(?string $type_contrat_souhaite): static
    {
        $this->type_contrat_souhaite = $type_contrat_souhaite;

        return $this;
    }

    public function getPretentionSalariale(): ?float
    {
        return $this->pretention_salariale;
    }

    public function setPretentionSalariale(?float $pretention_salariale): static
    {
        $this->pretention_salariale = $pretention_salariale;

        return $this;
    }

    public function getDateDisponibilite(): ?\DateTime
    {
        return $this->date_disponibilite;
    }

    public function setDateDisponibilite(?\DateTime $date_disponibilite): static
    {
        $this->date_disponibilite = $date_disponibilite;

        return $this;
    }

    public function getIdUtilisateur(): ?int
    {
        return $this->id_utilisateur;
    }

    public function setIdUtilisateur(?int $id_utilisateur): static
    {
        $this->id_utilisateur = $id_utilisateur;

        return $this;
    }
}

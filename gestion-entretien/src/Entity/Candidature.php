<?php

namespace App\Entity;

use App\Repository\CandidatureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CandidatureRepository::class)]
class Candidature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id_candidature = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\NotNull(message: "La date de candidature est obligatoire.")]
    #[Assert\LessThanOrEqual("today", message: "La date ne peut pas être dans le futur.")]
    private ?\DateTime $date_candidature = null;

    public function __construct()
    {
        $this->date_candidature = new \DateTime('today');
    }

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: "Le statut est obligatoire.")]
    #[Assert\Choice(
        choices: ["En attente", "Accepté", "Refusé"],
        message: "Statut invalide."
    )]
    private ?string $statut = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: "Le message ne peut pas dépasser 1000 caractères.")]

    private ?string $message_candidat = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cv = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lettre_motivation = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: "Le niveau d'expérience est obligatoire.")]
    #[Assert\Choice(
        choices: ["Débutant", "Junior", "Confirmé", "Senior", "Expert"],
        message: "Niveau d'expérience invalide."
    )]
    private ?string $niveau_experience = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "Les années d'expérience sont obligatoires.")]
    #[Assert\PositiveOrZero(message: "Les années d'expérience doivent être >= 0.")]
    #[Assert\LessThanOrEqual(50, message: "Les années d'expérience semblent incorrectes.")]
    private ?int $annees_experience = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: "Le domaine d'expérience est obligatoire.")]
    #[Assert\Length(max: 100, maxMessage: "Le domaine ne peut pas dépasser 100 caractères.")]

    private ?string $domaine_experience = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: "Le dernier poste ne peut pas dépasser 100 caractères.")]

    private ?string $dernier_poste = null;

    #[ORM\Column(nullable: true)]
    private ?int $id_utilisateur = 30;

    #[ORM\Column(nullable: true)]
    private ?int $id_offre = 1;

    public function getIdCandidature(): ?int
    {
        return $this->id_candidature;
    }

    public function getDateCandidature(): ?\DateTime
    {
        return $this->date_candidature;
    }

    public function setDateCandidature(?\DateTime $date_candidature): static
    {
        $this->date_candidature = $date_candidature;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getMessageCandidat(): ?string
    {
        return $this->message_candidat;
    }

    public function setMessageCandidat(?string $message_candidat): static
    {
        $this->message_candidat = $message_candidat;

        return $this;
    }

    public function getCv(): ?string
    {
        return $this->cv;
    }

    public function setCv(?string $cv): static
    {
        $this->cv = $cv;

        return $this;
    }

    public function getLettreMotivation(): ?string
    {
        return $this->lettre_motivation;
    }

    public function setLettreMotivation(?string $lettre_motivation): static
    {
        $this->lettre_motivation = $lettre_motivation;

        return $this;
    }

    public function getNiveauExperience(): ?string
    {
        return $this->niveau_experience;
    }

    public function setNiveauExperience(?string $niveau_experience): static
    {
        $this->niveau_experience = $niveau_experience;

        return $this;
    }

    public function getAnneesExperience(): ?int
    {
        return $this->annees_experience;
    }

    public function setAnneesExperience(?int $annees_experience): static
    {
        $this->annees_experience = $annees_experience;

        return $this;
    }

    public function getDomaineExperience(): ?string
    {
        return $this->domaine_experience;
    }

    public function setDomaineExperience(?string $domaine_experience): static
    {
        $this->domaine_experience = $domaine_experience;

        return $this;
    }

    public function getDernierPoste(): ?string
    {
        return $this->dernier_poste;
    }

    public function setDernierPoste(?string $dernier_poste): static
    {
        $this->dernier_poste = $dernier_poste;

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

    public function getIdOffre(): ?int
    {
        return $this->id_offre;
    }

    public function setIdOffre(?int $id_offre): static
    {
        $this->id_offre = $id_offre;

        return $this;
    }

    public function __toString(): string
    {
        return 'Candidature #' . $this->id_candidature . ' - ' . ($this->dernier_poste ?? $this->domaine_experience ?? 'N/A');
    }
}
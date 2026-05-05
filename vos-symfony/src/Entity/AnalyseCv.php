<?php

namespace App\Entity;

use App\Repository\AnalyseCvRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnalyseCvRepository::class)]
#[ORM\Table(name: 'analyse_cv')]
class AnalyseCv
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_analyse')]
    private ?int $id_analyse = null;

    #[ORM\ManyToOne(targetEntity: Candidature::class)]
    #[ORM\JoinColumn(name: 'id_candidature_id', referencedColumnName: 'id_candidature', nullable: false)]
    private ?Candidature $candidature = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    /** @var array<int|string, mixed>|null */
    private ?array $competences_detectees = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    /** @var array<int|string, mixed>|null */
    private ?array $points_forts = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    /** @var array<int|string, mixed>|null */
    private ?array $points_faibles = null;

    #[ORM\Column(nullable: true)]
    private ?int $score_cv = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    /** @var array<int|string, mixed>|null */
    private ?array $suggestions = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private \DateTimeImmutable $date_analyse;

    public function __construct()
    {
        $this->date_analyse = new \DateTimeImmutable();
    }

    public function getIdAnalyse(): ?int
    {
        return $this->id_analyse;
    }

    public function getIdCandidature(): ?int
    {
        return $this->candidature?->getIdCandidature();
    }

    public function getCandidature(): ?Candidature
    {
        return $this->candidature;
    }

    public function setCandidature(?Candidature $candidature): static
    {
        $this->candidature = $candidature;
        return $this;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function getCompetencesDetectees(): ?array
    {
        return $this->competences_detectees;
    }

    /**
     * @param array<int|string, mixed>|null $competences_detectees
     */
    public function setCompetencesDetectees(?array $competences_detectees): static
    {
        $this->competences_detectees = $competences_detectees;
        return $this;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function getPointsForts(): ?array
    {
        return $this->points_forts;
    }

    /**
     * @param array<int|string, mixed>|null $points_forts
     */
    public function setPointsForts(?array $points_forts): static
    {
        $this->points_forts = $points_forts;
        return $this;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function getPointsFaibles(): ?array
    {
        return $this->points_faibles;
    }

    /**
     * @param array<int|string, mixed>|null $points_faibles
     */
    public function setPointsFaibles(?array $points_faibles): static
    {
        $this->points_faibles = $points_faibles;
        return $this;
    }

    public function getScoreCv(): ?int
    {
        return $this->score_cv;
    }

    public function setScoreCv(?int $score_cv): static
    {
        $this->score_cv = $score_cv;
        return $this;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function getSuggestions(): ?array
    {
        return $this->suggestions;
    }

    /**
     * @param array<int|string, mixed>|null $suggestions
     */
    public function setSuggestions(?array $suggestions): static
    {
        $this->suggestions = $suggestions;
        return $this;
    }

    public function getDateAnalyse(): \DateTimeImmutable
    {
        return $this->date_analyse;
    }

    public function setDateAnalyse(\DateTimeImmutable $date_analyse): static
    {
        $this->date_analyse = $date_analyse;
        return $this;
    }
}

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

    #[ORM\Column(name: 'id_candidature')]
    private ?int $id_candidature = null;

    #[ORM\ManyToOne(targetEntity: Candidature::class)]
    #[ORM\JoinColumn(name: 'id_candidature', referencedColumnName: 'id_candidature', nullable: false)]
    private ?Candidature $candidature = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $competences_detectees = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $points_forts = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $points_faibles = null;

    #[ORM\Column(nullable: true)]
    private ?int $score_cv = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $suggestions = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $date_analyse = null;

    public function __construct()
    {
        $this->date_analyse = new \DateTime();
    }

    public function getIdAnalyse(): ?int
    {
        return $this->id_analyse;
    }

    public function getIdCandidature(): ?int
    {
        return $this->id_candidature;
    }

    public function setIdCandidature(?int $id_candidature): static
    {
        $this->id_candidature = $id_candidature;
        return $this;
    }

    public function getCandidature(): ?Candidature
    {
        return $this->candidature;
    }

    public function setCandidature(?Candidature $candidature): static
    {
        $this->candidature = $candidature;
        if ($candidature) {
            $this->id_candidature = $candidature->getIdCandidature();
        }
        return $this;
    }

    public function getCompetencesDetectees(): ?array
    {
        return $this->competences_detectees;
    }

    public function setCompetencesDetectees(?array $competences_detectees): static
    {
        $this->competences_detectees = $competences_detectees;
        return $this;
    }

    public function getPointsForts(): ?array
    {
        return $this->points_forts;
    }

    public function setPointsForts(?array $points_forts): static
    {
        $this->points_forts = $points_forts;
        return $this;
    }

    public function getPointsFaibles(): ?array
    {
        return $this->points_faibles;
    }

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

    public function getSuggestions(): ?array
    {
        return $this->suggestions;
    }

    public function setSuggestions(?array $suggestions): static
    {
        $this->suggestions = $suggestions;
        return $this;
    }

    public function getDateAnalyse(): ?\DateTime
    {
        return $this->date_analyse;
    }

    public function setDateAnalyse(?\DateTime $date_analyse): static
    {
        $this->date_analyse = $date_analyse;
        return $this;
    }
}

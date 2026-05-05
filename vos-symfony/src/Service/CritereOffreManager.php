<?php

namespace App\Service;

use App\Entity\CritereOffre;

class CritereOffreManager
{
    /**
     * Valide les règles métier des critères d'offre
     * 
     * Règles métier:
     * 1. Le niveau d'expérience est obligatoire et doit avoir 2-50 caractères
     * 2. Le niveau d'étude est obligatoire et doit avoir 2-50 caractères
     * 3. Les compétences requises sont obligatoires et doivent avoir 3-2000 caractères
     * 4. Les responsabilités sont obligatoires et doivent avoir 3-2000 caractères
     */
    public function validate(CritereOffre $critere): bool
    {
        // Règle 1: Le niveau d'expérience est obligatoire et doit avoir 2-50 caractères
        if (empty($critere->getNiveauExperience())) {
            throw new \InvalidArgumentException('Le niveau d\'expérience est obligatoire');
        }

        $experience = trim($critere->getNiveauExperience());
        if (strlen($experience) < 2) {
            throw new \InvalidArgumentException('Le niveau d\'expérience doit contenir au moins 2 caractères');
        }

        if (strlen($experience) > 50) {
            throw new \InvalidArgumentException('Le niveau d\'expérience ne peut pas dépasser 50 caractères');
        }

        // Règle 2: Le niveau d'étude est obligatoire et doit avoir 2-50 caractères
        if (empty($critere->getNiveauEtude())) {
            throw new \InvalidArgumentException('Le niveau d\'étude est obligatoire');
        }

        $etude = trim($critere->getNiveauEtude());
        if (strlen($etude) < 2) {
            throw new \InvalidArgumentException('Le niveau d\'étude doit contenir au moins 2 caractères');
        }

        if (strlen($etude) > 50) {
            throw new \InvalidArgumentException('Le niveau d\'étude ne peut pas dépasser 50 caractères');
        }

        // Règle 3: Les compétences requises sont obligatoires et doivent avoir 3-2000 caractères
        if (empty($critere->getCompetencesRequises())) {
            throw new \InvalidArgumentException('Les compétences requises sont obligatoires');
        }

        $competences = trim($critere->getCompetencesRequises());
        if (strlen($competences) < 3) {
            throw new \InvalidArgumentException('Les compétences requises doivent contenir au moins 3 caractères');
        }

        if (strlen($competences) > 2000) {
            throw new \InvalidArgumentException('Les compétences requises ne peuvent pas dépasser 2000 caractères');
        }

        // Règle 4: Les responsabilités sont obligatoires et doivent avoir 3-2000 caractères
        if (empty($critere->getResponsibilities())) {
            throw new \InvalidArgumentException('Les responsabilités sont obligatoires');
        }

        $responsibilities = trim($critere->getResponsibilities());
        if (strlen($responsibilities) < 3) {
            throw new \InvalidArgumentException('Les responsabilités doivent contenir au moins 3 caractères');
        }

        if (strlen($responsibilities) > 2000) {
            throw new \InvalidArgumentException('Les responsabilités ne peuvent pas dépasser 2000 caractères');
        }

        return true;
    }
}

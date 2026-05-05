<?php

namespace App\Service;

use App\Entity\OffreEmploi;

class OffreEmploiManager
{
    private const VALID_CONTRACT_TYPES = ['CDI', 'CDD', 'Stage', 'Alternance', 'Freelance'];
    private const VALID_STATUSES = ['OUVERTE', 'FERMEE', 'INACTIVE'];

    /**
     * Valide les règles métier de l'offre d'emploi
     * 
     * Règles métier:
     * 1. Le titre est obligatoire et doit avoir 3-100 caractères
     * 2. La date de publication ne peut pas être dans le passé
     * 3. Le type de contrat doit être parmi les valeurs autorisées
     * 4. Le statut de l'offre doit être parmi les valeurs autorisées
     */
    public function validate(OffreEmploi $offre): bool
    {
        // Règle 1: Le titre est obligatoire et doit avoir 3-100 caractères
        if (empty($offre->getTitre())) {
            throw new \InvalidArgumentException('Le titre de l\'offre est obligatoire');
        }

        $titre = trim($offre->getTitre());
        if (strlen($titre) < 3) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 3 caractères');
        }

        if (strlen($titre) > 100) {
            throw new \InvalidArgumentException('Le titre ne peut pas dépasser 100 caractères');
        }

        // Règle 2: La date de publication ne peut pas être dans le passé
        if ($offre->getDatePublication() === null) {
            throw new \InvalidArgumentException('La date de publication est obligatoire');
        }

        $today = new \DateTime('today');
        if ($offre->getDatePublication() < $today) {
            throw new \InvalidArgumentException('La date de publication ne peut pas être dans le passé');
        }

        // Règle 3: Le type de contrat doit être parmi les valeurs autorisées
        if (empty($offre->getTypeContrat())) {
            throw new \InvalidArgumentException('Le type de contrat est obligatoire');
        }

        if (!in_array($offre->getTypeContrat(), self::VALID_CONTRACT_TYPES, true)) {
            throw new \InvalidArgumentException('Le type de contrat doit être parmi : ' . implode(', ', self::VALID_CONTRACT_TYPES));
        }

        // Règle 4: Le statut de l'offre doit être parmi les valeurs autorisées
        if (empty($offre->getStatutOffre())) {
            throw new \InvalidArgumentException('Le statut de l\'offre est obligatoire');
        }

        if (!in_array($offre->getStatutOffre(), self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException('Le statut doit être parmi : ' . implode(', ', self::VALID_STATUSES));
        }

        return true;
    }
}

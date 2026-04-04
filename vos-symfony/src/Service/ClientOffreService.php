<?php

namespace App\Service;

class ClientOffreService
{
    // Offres statiques - données en dur uniquement
    public function getAllOffres(): array
    {
        return [
            [
                'id' => 1,
                'titre' => 'Développeur Full Stack PHP',
                'description' => 'Rejoignez notre équipe et travaillez sur des projets innovants avec les dernières technologies. Expérience requise: 3+ ans en PHP et JavaScript.',
                'entreprise' => 'TechCorp Solutions',
                'localisation' => 'Paris',
                'type' => 'CDI',
                'salaire' => 45000,
                'dateCreation' => new \DateTime('2026-04-01'),
            ],
            [
                'id' => 2,
                'titre' => 'Designer UX/UI',
                'description' => 'Créez des interfaces modernes et intuitives. Portfolio requis. Travail en équipe agile.',
                'entreprise' => 'CreativeDesign Inc',
                'localisation' => 'Lyon',
                'type' => 'CDI',
                'salaire' => 38000,
                'dateCreation' => new \DateTime('2026-04-02'),
            ],
            [
                'id' => 3,
                'titre' => 'Manager Commercial',
                'description' => 'Pilotez une équipe de 5 commerciaux. Objectifs ambitieux et encadrement bienveillant.',
                'entreprise' => 'SalesForce Pro',
                'localisation' => 'Toulouse',
                'type' => 'CDI',
                'salaire' => 50000,
                'dateCreation' => new \DateTime('2026-04-03'),
            ],
            [
                'id' => 4,
                'titre' => 'Stage Data Analyst',
                'description' => 'Découvrez le monde de la data. Apprentissage sur le terrain. Durée: 6 mois.',
                'entreprise' => 'DataMind Labs',
                'localisation' => 'Bordeaux',
                'type' => 'Stage',
                'salaire' => 0,
                'dateCreation' => new \DateTime('2026-04-04'),
            ],
            [
                'id' => 5,
                'titre' => 'Consultant SEO Freelance',
                'description' => 'Missions courtes et flexibles. Experts en référencement naturel bienvenue.',
                'entreprise' => 'SEO Masters',
                'localisation' => 'Français (Télétravail)',
                'type' => 'Freelance',
                'salaire' => 0,
                'dateCreation' => new \DateTime('2026-04-05'),
            ],
        ];
    }

    public function searchOffres(string $query = ''): array
    {
        $offres = $this->getAllOffres();

        if (empty($query)) {
            return $offres;
        }

        $query = strtolower($query);
        return array_filter($offres, function ($offre) use ($query) {
            return strpos(strtolower($offre['titre']), $query) !== false
                || strpos(strtolower($offre['entreprise']), $query) !== false
                || strpos(strtolower($offre['description']), $query) !== false;
        });
    }

    public function filterByType(string $type): array
    {
        if (empty($type)) {
            return $this->getAllOffres();
        }

        return array_filter($this->getAllOffres(), function ($offre) use ($type) {
            return $offre['type'] === $type;
        });
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize offre_emploi status values: replace ACTIVE with OUVERTE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE offre_emploi SET statut_offre = 'OUVERTE' WHERE UPPER(COALESCE(statut_offre, '')) = 'ACTIVE'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE offre_emploi SET statut_offre = 'ACTIVE' WHERE statut_offre = 'OUVERTE'");
    }
}

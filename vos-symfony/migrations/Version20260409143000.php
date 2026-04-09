<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set candidature.id_offre foreign key to ON DELETE CASCADE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY candidature_ibfk_2');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT candidature_ibfk_2 FOREIGN KEY (id_offre) REFERENCES offre_emploi (id_offre) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY candidature_ibfk_2');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT candidature_ibfk_2 FOREIGN KEY (id_offre) REFERENCES offre_emploi (id_offre)');
    }
}

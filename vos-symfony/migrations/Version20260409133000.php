<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set evaluation_entretien.id_entretien foreign key to ON DELETE CASCADE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evaluation_entretien DROP FOREIGN KEY evaluation_entretien_ibfk_1');
        $this->addSql('ALTER TABLE evaluation_entretien ADD CONSTRAINT evaluation_entretien_ibfk_1 FOREIGN KEY (id_entretien) REFERENCES entretien (id_entretien) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evaluation_entretien DROP FOREIGN KEY evaluation_entretien_ibfk_1');
        $this->addSql('ALTER TABLE evaluation_entretien ADD CONSTRAINT evaluation_entretien_ibfk_1 FOREIGN KEY (id_entretien) REFERENCES entretien (id_entretien)');
    }
}

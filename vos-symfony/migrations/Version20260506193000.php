<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore AUTO_INCREMENT generation for offre_emploi.id_offre';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offre_emploi MODIFY id_offre INT(11) NOT NULL AUTO_INCREMENT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE offre_emploi MODIFY id_offre INT(11) NOT NULL');
    }
}

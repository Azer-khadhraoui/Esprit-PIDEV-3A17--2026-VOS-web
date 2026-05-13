<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509100500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore AUTO_INCREMENT generation for utilisateur.id_utilisateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur MODIFY id_utilisateur INT(11) NOT NULL AUTO_INCREMENT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur MODIFY id_utilisateur INT(11) NOT NULL');
    }
}

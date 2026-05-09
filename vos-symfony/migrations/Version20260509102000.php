<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore AUTO_INCREMENT generation for recrutement.id_recrutement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recrutement MODIFY id_recrutement INT(11) NOT NULL AUTO_INCREMENT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recrutement MODIFY id_recrutement INT(11) NOT NULL');
    }
}

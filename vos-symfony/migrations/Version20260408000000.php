<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add cascade delete to entretien.evaluationEntretiens relation
 */
final class Version20260408000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cascade delete to entretien.evaluationEntretiens relation';
    }

    public function up(Schema $schema): void
    {
        // Drop the existing foreign key
        $this->addSql('ALTER TABLE `evaluation_entretien` DROP FOREIGN KEY `FK_97FEFC5F1F6D6446`');
        
        // Add it back with cascade delete
        $this->addSql('ALTER TABLE `evaluation_entretien` ADD CONSTRAINT `FK_97FEFC5F1F6D6446` FOREIGN KEY (`id_entretien`) REFERENCES `entretien` (`id_entretien`) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Revert to the original foreign key without cascade
        $this->addSql('ALTER TABLE `evaluation_entretien` DROP FOREIGN KEY `FK_97FEFC5F1F6D6446`');
        
        $this->addSql('ALTER TABLE `evaluation_entretien` ADD CONSTRAINT `FK_97FEFC5F1F6D6446` FOREIGN KEY (`id_entretien`) REFERENCES `entretien` (`id_entretien`)');
    }
}

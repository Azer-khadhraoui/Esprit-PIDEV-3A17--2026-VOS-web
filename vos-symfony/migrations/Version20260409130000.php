<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set contrat_embauche.id_recrutement foreign key to ON DELETE CASCADE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contrat_embauche DROP FOREIGN KEY contrat_embauche_ibfk_1');
        $this->addSql('ALTER TABLE contrat_embauche ADD CONSTRAINT contrat_embauche_ibfk_1 FOREIGN KEY (id_recrutement) REFERENCES recrutement (id_recrutement) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contrat_embauche DROP FOREIGN KEY contrat_embauche_ibfk_1');
        $this->addSql('ALTER TABLE contrat_embauche ADD CONSTRAINT contrat_embauche_ibfk_1 FOREIGN KEY (id_recrutement) REFERENCES recrutement (id_recrutement)');
    }
}

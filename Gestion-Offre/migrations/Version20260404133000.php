<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create offre_emploi and critere_offre tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE offre_emploi (id_offre INT AUTO_INCREMENT NOT NULL, titre VARCHAR(100) DEFAULT NULL, description LONGTEXT DEFAULT NULL, type_contrat VARCHAR(50) DEFAULT NULL, statut_offre VARCHAR(50) DEFAULT NULL, date_publication DATE DEFAULT NULL, id_utilisateur INT DEFAULT NULL, work_preference VARCHAR(50) DEFAULT NULL, lieu VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id_offre)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE critere_offre (id_critere INT AUTO_INCREMENT NOT NULL, niveau_experience VARCHAR(50) DEFAULT NULL, niveau_etude VARCHAR(50) DEFAULT NULL, competences_requises LONGTEXT DEFAULT NULL, responsibilities VARCHAR(2000) DEFAULT NULL, id_offre INT DEFAULT NULL, INDEX IDX_9D6F2B9FD15F3D7F (id_offre), PRIMARY KEY(id_critere)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE critere_offre ADD CONSTRAINT FK_9D6F2B9FD15F3D7F FOREIGN KEY (id_offre) REFERENCES offre_emploi (id_offre) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE critere_offre DROP FOREIGN KEY FK_9D6F2B9FD15F3D7F');
        $this->addSql('DROP TABLE critere_offre');
        $this->addSql('DROP TABLE offre_emploi');
    }
}
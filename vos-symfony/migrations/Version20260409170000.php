<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set ON DELETE CASCADE for gestion deletion chain (critere_offre, entretien, recrutement)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE critere_offre DROP FOREIGN KEY critere_offre_ibfk_1');
        $this->addSql('ALTER TABLE critere_offre ADD CONSTRAINT critere_offre_ibfk_1 FOREIGN KEY (id_offre) REFERENCES offre_emploi (id_offre) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE entretien DROP FOREIGN KEY entretien_ibfk_1');
        $this->addSql('ALTER TABLE entretien ADD CONSTRAINT entretien_ibfk_1 FOREIGN KEY (id_candidature) REFERENCES candidature (id_candidature) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE recrutement DROP FOREIGN KEY recrutement_ibfk_1');
        $this->addSql('ALTER TABLE recrutement ADD CONSTRAINT recrutement_ibfk_1 FOREIGN KEY (id_entretien) REFERENCES entretien (id_entretien) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recrutement DROP FOREIGN KEY recrutement_ibfk_1');
        $this->addSql('ALTER TABLE recrutement ADD CONSTRAINT recrutement_ibfk_1 FOREIGN KEY (id_entretien) REFERENCES entretien (id_entretien)');

        $this->addSql('ALTER TABLE entretien DROP FOREIGN KEY entretien_ibfk_1');
        $this->addSql('ALTER TABLE entretien ADD CONSTRAINT entretien_ibfk_1 FOREIGN KEY (id_candidature) REFERENCES candidature (id_candidature)');

        $this->addSql('ALTER TABLE critere_offre DROP FOREIGN KEY critere_offre_ibfk_1');
        $this->addSql('ALTER TABLE critere_offre ADD CONSTRAINT critere_offre_ibfk_1 FOREIGN KEY (id_offre) REFERENCES offre_emploi (id_offre)');
    }
}

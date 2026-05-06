<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505115000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename critere_offre foreign key column to match Doctrine _id convention';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE critere_offre DROP FOREIGN KEY critere_offre_ibfk_1');
        $this->addSql('ALTER TABLE critere_offre CHANGE id_offre id_offre_id INT(11) NOT NULL');
        $this->addSql('ALTER TABLE critere_offre DROP INDEX id_offre');
        $this->addSql('CREATE INDEX id_offre_id ON critere_offre (id_offre_id)');
        $this->addSql('ALTER TABLE critere_offre ADD CONSTRAINT FK_157498324103C75F FOREIGN KEY (id_offre_id) REFERENCES offre_emploi (id_offre) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE critere_offre DROP FOREIGN KEY FK_157498324103C75F');
        $this->addSql('ALTER TABLE critere_offre DROP INDEX id_offre_id');
        $this->addSql('ALTER TABLE critere_offre CHANGE id_offre_id id_offre INT(11) NOT NULL');
        $this->addSql('CREATE INDEX id_offre ON critere_offre (id_offre)');
        $this->addSql('ALTER TABLE critere_offre ADD CONSTRAINT critere_offre_ibfk_1 FOREIGN KEY (id_offre) REFERENCES offre_emploi (id_offre) ON DELETE CASCADE');
    }
}

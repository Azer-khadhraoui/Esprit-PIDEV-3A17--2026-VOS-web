<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add calendar_event_id to entretien';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entretien ADD calendar_event_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entretien DROP calendar_event_id');
    }
}

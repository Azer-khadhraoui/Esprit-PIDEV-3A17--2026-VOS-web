<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add calendar_event_id to recrutement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recrutement ADD calendar_event_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recrutement DROP calendar_event_id');
    }
}

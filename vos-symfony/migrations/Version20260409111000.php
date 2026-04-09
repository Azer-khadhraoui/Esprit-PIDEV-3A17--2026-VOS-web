<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409111000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add signature_url column to utilisateur when missing';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $table = $schemaManager->introspectTable('utilisateur');

        if (!$table->hasColumn('signature_url')) {
            $this->addSql('ALTER TABLE utilisateur ADD signature_url VARCHAR(500) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $table = $schemaManager->introspectTable('utilisateur');

        if ($table->hasColumn('signature_url')) {
            $this->addSql('ALTER TABLE utilisateur DROP COLUMN signature_url');
        }
    }
}

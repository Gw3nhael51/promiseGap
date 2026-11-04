<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261028204257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE documents ADD extraction_status VARCHAR(20) DEFAULT 'PENDING' NOT NULL");
        $this->addSql('ALTER TABLE documents ALTER COLUMN extraction_status DROP DEFAULT');
        $this->addSql('ALTER TABLE documents ADD extraction_error VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE documents ADD character_count INT DEFAULT NULL');
        $this->addSql('ALTER TABLE documents ADD estimated_tokens INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE documents DROP extraction_status');
        $this->addSql('ALTER TABLE documents DROP extraction_error');
        $this->addSql('ALTER TABLE documents DROP character_count');
        $this->addSql('ALTER TABLE documents DROP estimated_tokens');
    }
}

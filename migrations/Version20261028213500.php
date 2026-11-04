<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the document_chunks table with a pgvector embedding column for semantic RAG retrieval (#12)
 */
final class Version20261028213500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create document_chunks table with pgvector embedding(128) column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        $this->addSql(<<<'SQL'
            CREATE TABLE document_chunks (
                id SERIAL PRIMARY KEY,
                analysis_id INT NOT NULL REFERENCES analyses(id) ON DELETE CASCADE,
                document_id INT DEFAULT NULL REFERENCES documents(id) ON DELETE CASCADE,
                document_title VARCHAR(255) NOT NULL,
                section_title VARCHAR(255) NOT NULL,
                page_number INT NOT NULL DEFAULT 1,
                chunk_index INT NOT NULL DEFAULT 0,
                content TEXT NOT NULL,
                embedding vector(128) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
        $this->addSql('CREATE INDEX idx_document_chunks_analysis ON document_chunks (analysis_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS document_chunks');
    }
}

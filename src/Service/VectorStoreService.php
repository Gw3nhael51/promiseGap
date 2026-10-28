<?php

namespace App\Service;

use App\DTO\DocumentChunk;
use App\DTO\RetrievedChunk;
use App\Entity\Analysis;
use App\Enum\DocumentType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stores and queries document chunk embeddings in PostgreSQL using pgvector cosine similarity (US04 / #12)
 */
#[Autoconfigure(public: true)]
class VectorStoreService
{
    /**
     * @param Connection $connection Doctrine DBAL connection to PostgreSQL with pgvector
     * @param DocumentChunker $documentChunker Reference document chunking service
     * @param EmbeddingGenerator $embeddingGenerator Vector embedding generator
     * @param int $defaultTopK Default number of top chunks to retrieve
     */
    public function __construct(
        private Connection $connection,
        private DocumentChunker $documentChunker,
        private EmbeddingGenerator $embeddingGenerator,
        #[Autowire('%app.ai_store.top_k%')]
        private int $defaultTopK = 4,
    ) {
    }

    /**
     * Chunks and indexes all TECHNICAL_SPEC documents attached to an Analysis
     *
     * @param Analysis $analysis Target analysis workspace
     * @return int Total number of chunks indexed in pgvector
     */
    public function indexAnalysisDocuments(Analysis $analysis): int
    {
        $analysisId = $analysis->getId();
        if ($analysisId === null) {
            throw new \InvalidArgumentException('Analysis must be persisted before indexing chunks.');
        }

        $this->clearAnalysisIndex($analysisId);

        $allChunks = [];
        foreach ($analysis->getDocuments() as $document) {
            if ($document->getDocumentType() !== DocumentType::TECHNICAL_SPEC) {
                continue;
            }
            foreach ($this->documentChunker->chunkDocument($document) as $chunk) {
                $allChunks[] = $chunk;
            }
        }

        return $this->indexChunks($analysisId, $allChunks);
    }

    /**
     * Inserts a batch of DocumentChunk objects with their vector embeddings into PostgreSQL
     *
     * @param int $analysisId Owning Analysis ID
     * @param DocumentChunk[] $chunks Chunks to store
     * @return int Number of stored chunks
     */
    public function indexChunks(int $analysisId, array $chunks): int
    {
        $count = 0;
        $sql = <<<'SQL'
            INSERT INTO document_chunks (analysis_id, document_id, document_title, section_title, page_number, chunk_index, content, embedding)
            VALUES (:analysisId, :documentId, :documentTitle, :sectionTitle, :pageNumber, :chunkIndex, :content, :embedding::vector)
        SQL;

        foreach ($chunks as $chunk) {
            $vector = $this->embeddingGenerator->embed($chunk->sectionTitle . "\n" . $chunk->text);
            $this->connection->executeStatement($sql, [
                'analysisId' => $analysisId,
                'documentId' => $chunk->documentId,
                'documentTitle' => mb_substr($chunk->documentTitle, 0, 255),
                'sectionTitle' => mb_substr($chunk->sectionTitle, 0, 255),
                'pageNumber' => $chunk->pageNumber,
                'chunkIndex' => $chunk->chunkIndex,
                'content' => $chunk->text,
                'embedding' => $this->embeddingGenerator->toPgVectorLiteral($vector),
            ]);
            ++$count;
        }

        return $count;
    }

    /**
     * Performs a cosine similarity query in pgvector to retrieve the top-k relevant chunks for a commitment
     *
     * @param int $analysisId Analysis workspace ID
     * @param string $queryText Commercial commitment text
     * @param int|null $topK Number of chunks to retrieve (defaults to 4)
     * @return RetrievedChunk[] Ranked chunks ordered by descending cosine similarity
     */
    public function searchSimilar(int $analysisId, string $queryText, ?int $topK = null): array
    {
        $limit = max(1, min(10, $topK ?? $this->defaultTopK));
        $queryVector = $this->embeddingGenerator->toPgVectorLiteral($this->embeddingGenerator->embed($queryText));

        $sql = <<<'SQL'
            SELECT
                document_id,
                document_title,
                section_title,
                page_number,
                chunk_index,
                content,
                1 - (embedding <=> :queryVector::vector) AS similarity
            FROM document_chunks
            WHERE analysis_id = :analysisId
            ORDER BY embedding <=> :queryVector::vector ASC
            LIMIT :topK
        SQL;

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('queryVector', $queryVector);
        $stmt->bindValue('analysisId', $analysisId, \PDO::PARAM_INT);
        $stmt->bindValue('topK', $limit, \PDO::PARAM_INT);
        $rows = $stmt->executeQuery()->fetchAllAssociative();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new RetrievedChunk(
                text: (string) $row['content'],
                sectionTitle: (string) $row['section_title'],
                documentTitle: (string) $row['document_title'],
                pageNumber: (int) $row['page_number'],
                chunkIndex: (int) $row['chunk_index'],
                similarityScore: round(max(0.0, min(1.0, (float) $row['similarity'])), 4),
                documentId: $row['document_id'] !== null ? (int) $row['document_id'] : null,
            );
        }

        return $results;
    }

    /**
     * Removes all indexed chunks for a given analysis
     */
    public function clearAnalysisIndex(int $analysisId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM document_chunks WHERE analysis_id = :analysisId',
            ['analysisId' => $analysisId],
        );
    }
}

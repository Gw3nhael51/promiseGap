<?php

namespace App\DTO;

/**
 * Value object representing a reference chunk retrieved via pgvector cosine similarity search
 */
final readonly class RetrievedChunk
{
    /**
     * @param string $text Chunk text content
     * @param string $sectionTitle Heading or section title
     * @param string $documentTitle Source document title or filename
     * @param int $pageNumber Source page number
     * @param int $chunkIndex Position of the chunk within the document
     * @param float $similarityScore Cosine similarity score between 0.0 and 1.0
     * @param int|null $documentId Optional database ID of the source Document entity
     */
    public function __construct(
        public string $text,
        public string $sectionTitle,
        public string $documentTitle,
        public int $pageNumber = 1,
        public int $chunkIndex = 0,
        public float $similarityScore = 0.0,
        public ?int $documentId = null,
    ) {
    }
}

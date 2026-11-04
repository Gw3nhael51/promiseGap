<?php

namespace App\DTO;

/**
 * Value object representing a semantic chunk of a technical reference document
 */
final readonly class DocumentChunk
{
    /**
     * @param string $text Cleaned textual content of the chunk
     * @param string $sectionTitle Heading hierarchy or section title
     * @param string $documentTitle Title or filename of the source document
     * @param int $pageNumber Source page number
     * @param int $chunkIndex Zero-based position of the chunk within the document
     * @param int $tokenEstimate Estimated number of tokens in the chunk
     * @param int|null $documentId Optional database identifier of the source Document entity
     */
    public function __construct(
        public string $text,
        public string $sectionTitle,
        public string $documentTitle,
        public int $pageNumber = 1,
        public int $chunkIndex = 0,
        public int $tokenEstimate = 0,
        public ?int $documentId = null,
    ) {
    }
}

<?php

namespace App\DTO;

/**
 * Data transfer object representing a validated source evidence quote
 */
final readonly class EvidenceDTO
{
    /**
     * @param string $quote Verbatim quote from the technical specification
     * @param string $sectionTitle Heading or section title
     * @param int $pageNumber Source page number
     * @param float $similarityScore Cosine similarity score
     * @param int|null $documentId Optional source Document entity ID
     */
    public function __construct(
        public string $quote,
        public string $sectionTitle = 'Technical Specification',
        public int $pageNumber = 1,
        public float $similarityScore = 0.0,
        public ?int $documentId = null,
    ) {
    }
}

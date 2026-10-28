<?php

namespace App\DTO;

/**
 * Holds the list of commercial commitments extracted from a proposal
 */
final readonly class CommitmentExtractionResult
{
    /**
     * @param CommitmentDTO[] $commitments Extracted commitments
     */
    public function __construct(
        public array $commitments,
    ) {
    }

    /**
     * Returns the number of extracted commitments
     */
    public function count(): int
    {
        return count($this->commitments);
    }
}

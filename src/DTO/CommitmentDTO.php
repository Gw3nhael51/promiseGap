<?php

namespace App\DTO;

/**
 * Data transfer object representing a single extracted commercial commitment
 */
final readonly class CommitmentDTO
{
    /**
     * @param string $verbatimText Exact verbatim commitment quote from the proposal
     * @param string $category Functional category (SLA, SUPPORT, SECURITY, DELIVERY, GENERAL)
     * @param float $confidenceScore Extraction confidence score between 0.0 and 1.0
     * @param int $sourcePage Page number in the source proposal
     */
    public function __construct(
        public string $verbatimText,
        public string $category = 'GENERAL',
        public float $confidenceScore = 0.9,
        public int $sourcePage = 1,
    ) {
    }
}

<?php

namespace App\DTO;

use App\Enum\FindingStatus;

/**
 * Value object holding the validated discrepancy qualification for a commitment
 */
final readonly class QualificationResult
{
    /**
     * @param FindingStatus $status Qualification category (SUPPORTED, UNSUPPORTED, CONTRADICTORY, AMBIGUOUS)
     * @param string $rationale Detailed explanation of the verdict
     * @param EvidenceDTO[] $evidences Verbatim source evidence items
     */
    public function __construct(
        public FindingStatus $status,
        public string $rationale,
        public array $evidences = [],
    ) {
    }
}

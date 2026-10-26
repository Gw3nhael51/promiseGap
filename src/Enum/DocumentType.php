<?php

namespace App\Enum;

/**
 * Defines accepted document types in an analysis workspace
 */
enum DocumentType: string
{
    case PROPOSAL = 'PROPOSAL';
    case TECHNICAL_SPEC = 'TECHNICAL_SPEC';

    /**
     * Returns human-readable label for the document type
     *
     * @return string Human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::PROPOSAL => 'Commercial Proposal',
            self::TECHNICAL_SPEC => 'Technical Specification',
        };
    }
}

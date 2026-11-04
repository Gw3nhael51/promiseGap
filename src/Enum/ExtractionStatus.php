<?php

namespace App\Enum;

/**
 * Represents the text extraction state of an uploaded document
 */
enum ExtractionStatus: string
{
    case PENDING = 'PENDING';
    case DONE = 'DONE';
    case FAILED = 'FAILED';

    /**
     * Returns a human readable label for the status
     *
     * @return string Status label
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Extracting',
            self::DONE => 'Text extracted',
            self::FAILED => 'Extraction failed',
        };
    }

    /**
     * Returns the DaisyUI badge class matching the status
     *
     * @return string CSS badge class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'badge-warning',
            self::DONE => 'badge-success',
            self::FAILED => 'badge-error',
        };
    }
}

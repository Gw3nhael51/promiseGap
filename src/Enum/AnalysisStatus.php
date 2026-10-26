<?php

namespace App\Enum;

/**
 * Defines possible statuses for an analysis workspace
 */
enum AnalysisStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';

    /**
     * Returns human-readable label for the status
     *
     * @return string Human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
        };
    }

    /**
     * Returns DaisyUI CSS badge class corresponding to the status
     *
     * @return string CSS class for badge styling
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'badge-neutral',
            self::PROCESSING => 'badge-warning',
            self::COMPLETED => 'badge-success',
            self::FAILED => 'badge-error',
        };
    }
}

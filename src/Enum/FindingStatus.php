<?php

namespace App\Enum;

/**
 * Qualification categories for commercial commitment vs technical specification comparisons
 */
enum FindingStatus: string
{
    case SUPPORTED = 'SUPPORTED';
    case UNSUPPORTED = 'UNSUPPORTED';
    case CONTRADICTORY = 'CONTRADICTORY';
    case AMBIGUOUS = 'AMBIGUOUS';

    /**
     * Returns a human-readable display label
     *
     * @return string Status label
     */
    public function label(): string
    {
        return match ($this) {
            self::SUPPORTED => 'Supported',
            self::UNSUPPORTED => 'Unsupported',
            self::CONTRADICTORY => 'Contradictory',
            self::AMBIGUOUS => 'Ambiguous',
        };
    }

    /**
     * Returns the 3-letter UI filter code used in the Results drawer
     *
     * @return string Short code (sup, uns, con, amb)
     */
    public function shortCode(): string
    {
        return match ($this) {
            self::SUPPORTED => 'sup',
            self::UNSUPPORTED => 'uns',
            self::CONTRADICTORY => 'con',
            self::AMBIGUOUS => 'amb',
        };
    }

    /**
     * Returns the DaisyUI badge class for the status
     *
     * @return string CSS badge class
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::SUPPORTED => 'badge-success',
            self::UNSUPPORTED => 'badge-error',
            self::CONTRADICTORY => 'badge-warning',
            self::AMBIGUOUS => 'badge-info',
        };
    }
}

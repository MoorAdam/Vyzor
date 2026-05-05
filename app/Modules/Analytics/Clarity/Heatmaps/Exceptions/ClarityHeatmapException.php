<?php

namespace App\Modules\Analytics\Clarity\Heatmaps\Exceptions;

use RuntimeException;

/**
 * Thrown when a CSV upload fails to look like a genuine Microsoft Clarity
 * heatmap export. The `reason` is a stable machine-readable code that the
 * caller maps to a localized user-facing message.
 */
class ClarityHeatmapException extends RuntimeException
{
    public const REASON_EMPTY = 'empty';
    public const REASON_BINARY = 'binary';
    public const REASON_TOO_LARGE = 'too_large';
    public const REASON_NO_DATE_RANGE = 'no_date_range';
    public const REASON_INVALID_DATE = 'invalid_date';
    public const REASON_SIGNATURE_MISMATCH = 'signature_mismatch';

    public function __construct(
        public readonly string $reason,
        public readonly array $context = [],
    ) {
        parent::__construct($reason);
    }
}

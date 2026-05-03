<?php

declare(strict_types=1);

namespace InfilePhp\Core\Enums;

/**
 * Infile certification flow selection.
 *
 * Unified: sign + certify in a single HTTP call.
 * Separate: sign first, then certify in a second HTTP call.
 */
enum Flow: string
{
    case Unified = 'unified';
    case Separate = 'separate';
}

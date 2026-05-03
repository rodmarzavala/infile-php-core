<?php

declare(strict_types=1);

namespace InfilePhp\Core\Exceptions;

/**
 * Thrown when the SDK fails to generate a CAFE (local access number) during contingency mode.
 */
final class CafeGenerationException extends ContingencyException
{
}

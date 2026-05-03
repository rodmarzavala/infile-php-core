<?php

declare(strict_types=1);

namespace InfilePhp\Core\Exceptions;

/**
 * Thrown when enqueueing a DTE for later certification fails during contingency mode.
 */
final class QueueException extends ContingencyException
{
}

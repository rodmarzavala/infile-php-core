<?php

declare(strict_types=1);

namespace InfilePhp\Core\Exceptions;

/**
 * Thrown when the daily API call limit (2,000) has been reached.
 *
 * Check $response->remainingCredits() proactively to avoid this exception.
 */
final class DailyLimitExceededException extends InfileException
{
    public function __construct(
        public readonly int $dailyLimit,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: "The Infile daily API call limit of {$dailyLimit} has been exceeded. Try again tomorrow.",
            statusCode: 429,
            previous: $previous,
        );
    }
}

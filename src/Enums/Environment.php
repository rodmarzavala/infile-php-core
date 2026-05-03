<?php

declare(strict_types=1);

namespace InfilePhp\Core\Enums;

/**
 * SDK operating environment.
 */
enum Environment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    /**
     * Determine whether this is a production environment.
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }
}

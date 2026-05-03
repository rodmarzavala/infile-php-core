<?php

declare(strict_types=1);

namespace InfilePhp\Core\Support;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * No-op event dispatcher used when no real dispatcher has been configured.
 * Allows the core to fire events without a framework dependency.
 */
final class NullEventDispatcher implements EventDispatcherInterface
{
    /**
     * Accept an event and do nothing with it.
     *
     * @template T of object
     * @param T $event
     * @return T
     */
    public function dispatch(object $event): object
    {
        return $event;
    }
}

<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fired around a card disclosure so applications can plug their own audit
 * storage, notifications or metrics.
 */
final class ClientDiscloseEvent extends Event
{
    /**
     * Dispatched right before the value is revealed, once the policy passed.
     */
    public const ATTEMPT = 'client_card.attempt';

    /**
     * Dispatched after the value was revealed.
     */
    public const SUCCESS = 'client_card.success';

    /**
     * Dispatched when the reveal is rejected (rate limit or access denied);
     * the status carries the exact reason.
     */
    public const REJECTED = 'client_card.rejected';

    public function __construct(
        public readonly string $class,
        public readonly string|int $id,
        public readonly string $status,
        public readonly ?object $subject = null,
    ) {}
}

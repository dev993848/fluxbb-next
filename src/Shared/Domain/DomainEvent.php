<?php

declare(strict_types=1);

namespace FluxBB\Shared\Domain;

/**
 * Base Domain Event marker interface.
 *
 * Domain Events represent something meaningful that happened in the domain.
 * They are immutable records of past events and are named in the past tense
 * (e.g., UserRegistered, PostCreated, UserBanned).
 *
 * Events are published by Aggregate Roots and dispatched through an
 * Event Dispatcher to any interested listeners, potentially in other
 * Bounded Contexts.
 */
interface DomainEvent
{
    /**
     * Get the timestamp when this event occurred.
     *
     * @return \DateTimeImmutable The immutable timestamp of the event
     */
    public function occurredAt(): \DateTimeImmutable;

    /**
     * Get the identity of the aggregate root that produced this event.
     *
     * This allows event listeners to correlate events back to the
     * source aggregate if needed.
     *
     * @return mixed The aggregate root's identity value
     */
    public function aggregateId(): mixed;
}
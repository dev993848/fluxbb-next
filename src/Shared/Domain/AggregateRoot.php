<?php

declare(strict_types=1);

namespace FluxBB\Shared\Domain;

/**
 * Base Aggregate Root class.
 *
 * Aggregate Roots are the consistency boundaries in the domain model.
 * They are the only entities that can be directly queried and persisted;
 * all other entities within the aggregate are accessed through the root.
 *
 * Aggregate Roots record Domain Events when their state changes.
 * These events are released (cleared) after being dispatched to the
 * event bus, typically by the infrastructure layer after persistence.
 */
abstract class AggregateRoot implements Entity
{
    /**
     * Recorded domain events that have not yet been released.
     *
     * @var list<object>
     */
    private array $domainEvents = [];

    /**
     * Record a domain event that just happened within this aggregate.
     *
     * Events are stored in memory until they are released via releaseEvents().
     * This is typically called by command handlers in the Application layer
     * after the aggregate has been persisted.
     *
     * @param object $event The domain event to record
     */
    protected function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * Release all recorded domain events and clear the internal queue.
     *
     * This method should be called after the aggregate has been saved
     * to its repository, ensuring all events are dispatched exactly once.
     *
     * @return list<object> The list of recorded domain events
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    /**
     * Clear all recorded events without releasing them.
     *
     * Useful when an aggregate is reconstructed from persistence and
     * its recorded events should not be re-dispatched.
     */
    public function clearEvents(): void
    {
        $this->domainEvents = [];
    }
}
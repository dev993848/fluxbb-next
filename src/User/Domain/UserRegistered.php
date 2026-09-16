<?php

declare(strict_types=1);

namespace FluxBB\User\Domain;

use FluxBB\Shared\Domain\DomainEvent;

/**
 * Domain event: a new user has registered.
 *
 * This event is published after the user is persisted and can be used
 * by other Bounded Contexts for:
 * - Sending a welcome email
 * - Creating default subscriptions (e.g., to an announcement forum)
 * - Moderation: checking for duplicate registrations (same IP)
 */
class UserRegistered implements DomainEvent
{
    /**
     * @param UserId               $userId     The newly created user's ID
     * @param Username             $username   The chosen username
     * @param Email                $email      The registered email
     * @param \DateTimeImmutable   $occurredAt When the registration happened
     */
    public function __construct(
        public readonly UserId $userId,
        public readonly Username $username,
        public readonly Email $email,
        private readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    /**
     * Get the timestamp when the user registered.
     *
     * @return \DateTimeImmutable The event timestamp
     */
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * Get the aggregate root ID that produced this event.
     *
     * @return UserId The new user's ID
     */
    public function aggregateId(): UserId
    {
        return $this->userId;
    }
}
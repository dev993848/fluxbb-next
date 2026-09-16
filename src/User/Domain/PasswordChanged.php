<?php

declare(strict_types=1);

namespace FluxBB\User\Domain;

use FluxBB\Shared\Domain\DomainEvent;

/**
 * Domain event: a user changed their password.
 *
 * This event can be used to:
 * - Invalidate the user's existing sessions (force re-login)
 * - Notify the user via email about the password change
 * - Log the event for security auditing
 */
class PasswordChanged implements DomainEvent
{
    /**
     * @param UserId             $userId     The user whose password changed
     * @param \DateTimeImmutable $occurredAt When the change occurred
     */
    public function __construct(
        public readonly UserId $userId,
        private readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    /**
     * Get the timestamp of the password change.
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
     * @return UserId The user's ID
     */
    public function aggregateId(): UserId
    {
        return $this->userId;
    }
}
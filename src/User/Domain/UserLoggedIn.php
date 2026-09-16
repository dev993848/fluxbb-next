<?php

declare(strict_types=1);

namespace FluxBB\User\Domain;

use FluxBB\Shared\Domain\DomainEvent;

/**
 * Domain event: a user successfully logged in.
 *
 * Published each time a user authenticates. Can be used by the online
 * tracker to update the "users online" list and by security monitoring
 * to detect brute-force or unusual login patterns.
 */
class UserLoggedIn implements DomainEvent
{
    /**
     * @param UserId             $userId     The user who logged in
     * @param \DateTimeImmutable $occurredAt When the login occurred
     */
    public function __construct(
        public readonly UserId $userId,
        private readonly \DateTimeImmutable $occurredAt,
    ) {}

    /**
     * Get the timestamp of the login.
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
     * @return UserId The logged-in user's ID
     */
    public function aggregateId(): UserId
    {
        return $this->userId;
    }
}
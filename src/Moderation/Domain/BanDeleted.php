<?php

declare(strict_types=1);

namespace FluxBB\Moderation\Domain;

use FluxBB\Shared\Domain\DomainEvent;

/**
 * Domain event: a ban was deleted.
 *
 * Triggers cache invalidation so the lifts take effect immediately.
 */
class BanDeleted implements DomainEvent
{
    /**
     * @param int                  $banId      The deleted ban's ID
     * @param string               $reason     Why the ban was deleted
     * @param \DateTimeImmutable   $occurredAt When the deletion happened
     */
    public function __construct(
        public readonly int $banId,
        public readonly string $reason = 'Manual deletion',
        private readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return $this->banId;
    }
}
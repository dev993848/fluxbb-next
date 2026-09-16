<?php

declare(strict_types=1);

namespace FluxBB\Moderation\Domain;

use FluxBB\Shared\Domain\DomainEvent;

/**
 * Domain event: a new ban was created.
 *
 * Triggers cache invalidation for the bans cache (matching the original
 * cache_bans.php pattern) so that the new ban takes effect immediately
 * without requiring a manual cache clear.
 */
class BanCreated implements DomainEvent
{
    /**
     * @param int                  $banId      The new ban's ID
     * @param \DateTimeImmutable   $occurredAt When the ban was created
     */
    public function __construct(
        public readonly int $banId,
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
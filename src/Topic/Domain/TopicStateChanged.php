<?php

declare(strict_types=1);

namespace FluxBB\Topic\Domain;

use FluxBB\Shared\Domain\DomainEvent;

/**
 * Domain event: topic state has changed (closed, reopened, stuck, unstuck).
 */
class TopicStateChanged implements DomainEvent
{
    /**
     * @param int                $topicId    The topic ID
     * @param string             $newState   The new state ('closed', 'reopened', 'stuck', 'unstuck')
     * @param \DateTimeImmutable $occurredAt When the change happened
     */
    public function __construct(
        public readonly int $topicId,
        public readonly string $newState,
        private readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function aggregateId(): int
    {
        return $this->topicId;
    }
}
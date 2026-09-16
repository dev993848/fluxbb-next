<?php

declare(strict_types=1);

namespace FluxBB\Post\Domain;

use FluxBB\Shared\Domain\DomainEvent;

/**
 * Domain event: a post was edited.
 *
 * Published after a successful edit. Other Bounded Contexts (Moderation,
 * Subscription) may subscribe to this event for notification purposes.
 */
class PostEdited implements DomainEvent
{
    /**
     * @param int                $postId      The edited post ID
     * @param int                $editorId    The user who performed the edit
     * @param \DateTimeImmutable $editedAt    When the edit occurred
     */
    public function __construct(
        public readonly int $postId,
        public readonly int $editorId,
        private readonly \DateTimeImmutable $editedAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->editedAt;
    }

    public function aggregateId(): int
    {
        return $this->postId;
    }
}
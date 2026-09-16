<?php

declare(strict_types=1);

namespace FluxBB\Topic\Domain;

use FluxBB\Shared\Domain\Entity;

/**
 * Topic entity.
 *
 * Represents a discussion topic within a forum. A topic contains posts
 * and tracks its first post, last post, and various moderation flags.
 */
class Topic implements Entity
{
    /**
     * @param int                 $id            Unique topic identifier
     * @param string              $subject       Topic subject/title
     * @param int                 $forumId       Parent forum ID
     * @param int                 $posterId      ID of the user who created the topic
     * @param string              $poster        Username of the creator
     * @param \DateTimeImmutable  $postedAt      When the topic was created
     * @param int                 $firstPostId   ID of the first post in the topic
     * @param int                 $lastPostId    ID of the most recent post
     * @param int                 $lastPosterId  ID of the most recent poster
     * @param string              $lastPoster    Username of the most recent poster
     * @param \DateTimeImmutable  $lastPostedAt  Timestamp of the most recent post
     * @param int                 $numReplies    Number of replies (excluding first post)
     * @param bool                $closed        Whether the topic is closed for new replies
     * @param bool                $sticky        Whether the topic is sticky (always on top)
     * @param bool                $moved         Whether the topic has been moved to another forum
     * @param int|null            $movedToForum  If moved, the destination forum ID
     */
    public function __construct(
        private readonly int $id,
        private readonly string $subject,
        private readonly int $forumId,
        private readonly int $posterId,
        private readonly string $poster,
        private readonly \DateTimeImmutable $postedAt,
        private int $firstPostId,
        private int $lastPostId,
        private int $lastPosterId,
        private string $lastPoster,
        private \DateTimeImmutable $lastPostedAt,
        private int $numReplies = 0,
        private bool $closed = false,
        private bool $sticky = false,
        private bool $moved = false,
        private ?int $movedToForum = null,
    ) {}

    public function identity(): int
    {
        return $this->id;
    }

    public function getId(): int { return $this->id; }
    public function getSubject(): string { return $this->subject; }
    public function getForumId(): int { return $this->forumId; }
    public function getPosterId(): int { return $this->posterId; }
    public function getPoster(): string { return $this->poster; }
    public function getPostedAt(): \DateTimeImmutable { return $this->postedAt; }
    public function getFirstPostId(): int { return $this->firstPostId; }
    public function getLastPostId(): int { return $this->lastPostId; }
    public function getLastPosterId(): int { return $this->lastPosterId; }
    public function getLastPoster(): string { return $this->lastPoster; }
    public function getLastPostedAt(): \DateTimeImmutable { return $this->lastPostedAt; }
    public function getNumReplies(): int { return $this->numReplies; }
    public function isClosed(): bool { return $this->closed; }
    public function isSticky(): bool { return $this->sticky; }
    public function isMoved(): bool { return $this->moved; }
    public function getMovedToForum(): ?int { return $this->movedToForum; }

    /**
     * Update last post information after a new reply.
     */
    public function updateLastPost(int $postId, int $posterId, string $poster, \DateTimeImmutable $postedAt): void
    {
        $this->lastPostId = $postId;
        $this->lastPosterId = $posterId;
        $this->lastPoster = $poster;
        $this->lastPostedAt = $postedAt;
        $this->numReplies++;
    }

    /**
     * Close the topic (prevent new replies).
     */
    public function close(): void
    {
        $this->closed = true;
        $this->recordEvent(new TopicStateChanged($this->id, 'closed'));
    }

    /**
     * Reopen a closed topic.
     */
    public function reopen(): void
    {
        $this->closed = false;
        $this->recordEvent(new TopicStateChanged($this->id, 'reopened'));
    }

    /**
     * Make the topic sticky.
     */
    public function stick(): void
    {
        $this->sticky = true;
        $this->recordEvent(new TopicStateChanged($this->id, 'stuck'));
    }

    /**
     * Unstick a sticky topic.
     */
    public function unstick(): void
    {
        $this->sticky = false;
        $this->recordEvent(new TopicStateChanged($this->id, 'unstuck'));
    }

    /**
     * Record a domain event for state changes.
     */
    private function recordEvent(object $event): void
    {
        // Events are stored via the AggregateRoot trait when project uses it
    }
}
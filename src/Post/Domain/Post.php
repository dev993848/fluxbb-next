<?php

declare(strict_types=1);

namespace FluxBB\Post\Domain;

use FluxBB\Shared\Domain\AggregateRoot;

/**
 * Post entity.
 *
 * Represents a single post within a topic. The entity carries domain scars
 * related to edit permissions and edit timeouts.
 *
 * Domain scars:
 * - Edit timeout: a post can only be edited within o_edit_timeout seconds
 *   of being posted (unless the user is an admin/moderator).
 * - Edit permission: a user can only edit their own post, unless they are
 *   an admin or a moderator.
 *
 * @see https://github.com/fluxbb/fluxbb/issues/44
 * @see https://github.com/fluxbb/fluxbb/issues/105
 */
class Post extends AggregateRoot
{
    /**
     * @param int                 $id            Unique post identifier
     * @param int                 $topicId       Parent topic ID
     * @param int                 $forumId       Parent forum ID (denormalized)
     * @param int                 $posterId      ID of the post author
     * @param string              $poster        Username of the author
     * @param string              $message       Post content (raw BBCode)
     * @param \DateTimeImmutable  $postedAt      When the post was created
     * @param int|null            $editedBy      ID of the user who last edited (null if never)
     * @param \DateTimeImmutable|null $editedAt  When the post was last edited (null if never)
     * @param bool                $hideSmilies   Whether to disable smilies in this post
     * @param string|null         $posterIp      IP address of the author at post time
     */
    public function __construct(
        private readonly int $id,
        private readonly int $topicId,
        private readonly int $forumId,
        private readonly int $posterId,
        private readonly string $poster,
        private string $message,
        private readonly \DateTimeImmutable $postedAt,
        private ?int $editedBy = null,
        private ?\DateTimeImmutable $editedAt = null,
        private bool $hideSmilies = false,
        private ?string $posterIp = null,
    ) {}

    public function identity(): int
    {
        return $this->id;
    }

    public function getId(): int { return $this->id; }
    public function getTopicId(): int { return $this->topicId; }
    public function getForumId(): int { return $this->forumId; }
    public function getPosterId(): int { return $this->posterId; }
    public function getPoster(): string { return $this->poster; }
    public function getMessage(): string { return $this->message; }
    public function getPostedAt(): \DateTimeImmutable { return $this->postedAt; }
    public function getEditedBy(): ?int { return $this->editedBy; }
    public function getEditedAt(): ?\DateTimeImmutable { return $this->editedAt; }
    public function getHideSmilies(): bool { return $this->hideSmilies; }
    public function getPosterIp(): ?string { return $this->posterIp; }

    /**
     * Get the Unix timestamp of the post creation time.
     *
     * @return int Unix timestamp
     */
    public function getPostedTimestamp(): int
    {
        return $this->postedAt->getTimestamp();
    }

    /**
     * Get the edit timeout in seconds from the config.
     * Default is 0 (no timeout, can always edit).
     *
     * @param int $editTimeout Configured edit timeout in seconds
     * @return bool True if the post can still be edited by its author
     */
    public function isWithinEditTimeout(int $editTimeout): bool
    {
        if ($editTimeout === 0) {
            return true;
        }

        $elapsed = time() - $this->getPostedTimestamp();
        return $elapsed <= $editTimeout;
    }

    /**
     * Edit the post message.
     *
     * Updates the message content and records the editor's ID and timestamp.
     *
     * @param string                     $newMessage The new message content
     * @param int                        $editorId   The ID of the user editing
     * @param \DateTimeImmutable|null    $editedAt   When the edit occurred
     */
    public function edit(string $newMessage, int $editorId, ?\DateTimeImmutable $editedAt = null): void
    {
        $this->message = $newMessage;
        $this->editedBy = $editorId;
        $this->editedAt = $editedAt ?? new \DateTimeImmutable();

        $this->recordEvent(new PostEdited($this->id, $editorId, $this->editedAt));
    }
}
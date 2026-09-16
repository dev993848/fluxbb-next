<?php

declare(strict_types=1);

namespace FluxBB\Forum\Domain;

use FluxBB\Shared\Domain\Entity;

/**
 * Forum entity.
 *
 * Represents a forum within a category. Each forum belongs to exactly one
 * category, has a display position, and tracks its last post statistics.
 * Forums can be regular discussion forums or redirect URLs.
 *
 * The entity is read-only from the domain perspective — mutations go through
 * the Forum aggregate command handlers.
 */
class Forum implements Entity
{
    /**
     * @param int                 $id            Unique forum identifier
     * @param string              $name          Display name of the forum
     * @param string              $description   Short description shown below the name
     * @param int                 $categoryId    Parent category ID
     * @param int                 $position      Sort order within the category
     * @param int|null            $lastPostId    ID of the most recent post
     * @param int|null            $lastPosterId  ID of the most recent poster
     * @param string|null         $lastPosterName Username of the most recent poster
     * @param \DateTimeImmutable|null $lastPosted Timestamp of the most recent post
     * @param int                 $numTopics     Cached topic count
     * @param int                 $numPosts      Cached post count
     * @param string              $redirectUrl   If non-empty, forum redirects here
     * @param string|null         $moderators    Serialized array of moderator usernames
     */
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $description,
        private readonly int $categoryId,
        private readonly int $position = 0,
        private readonly ?int $lastPostId = null,
        private readonly ?int $lastPosterId = null,
        private readonly ?string $lastPosterName = null,
        private readonly ?\DateTimeImmutable $lastPosted = null,
        private readonly int $numTopics = 0,
        private readonly int $numPosts = 0,
        private readonly string $redirectUrl = '',
        private readonly ?string $moderators = null,
    ) {}

    /**
     * Get the forum's unique identifier.
     *
     * @return int The forum ID
     */
    public function identity(): int
    {
        return $this->id;
    }

    /** @return int The forum ID */
    public function getId(): int { return $this->id; }

    /** @return string The forum display name */
    public function getName(): string { return $this->name; }

    /** @return string The forum description */
    public function getDescription(): string { return $this->description; }

    /** @return int The parent category ID */
    public function getCategoryId(): int { return $this->categoryId; }

    /** @return int The sort position within the category */
    public function getPosition(): int { return $this->position; }

    /** @return int|null The last post ID */
    public function getLastPostId(): ?int { return $this->lastPostId; }

    /** @return int|null The last poster's user ID */
    public function getLastPosterId(): ?int { return $this->lastPosterId; }

    /** @return string|null The last poster's username */
    public function getLastPosterName(): ?string { return $this->lastPosterName; }

    /** @return \DateTimeImmutable|null The last post timestamp */
    public function getLastPosted(): ?\DateTimeImmutable { return $this->lastPosted; }

    /** @return int The cached topic count */
    public function getNumTopics(): int { return $this->numTopics; }

    /** @return int The cached post count */
    public function getNumPosts(): int { return $this->numPosts; }

    /**
     * Check if this forum is a redirect link.
     *
     * @return bool True if the forum redirects to an external URL
     */
    public function isRedirect(): bool { return $this->redirectUrl !== ''; }

    /** @return string The redirect URL (empty if not a redirect forum) */
    public function getRedirectUrl(): string { return $this->redirectUrl; }

    /**
     * Get the serialized moderators string.
     * Unserialize to get the array of moderator usernames.
     *
     * @return string|null Serialized moderator data
     */
    public function getModerators(): ?string { return $this->moderators; }
}
<?php

declare(strict_types=1);

namespace FluxBB\Post\Domain;

/**
 * Event fired when a new post is created.
 */
final class PostCreated
{
    public function __construct(
        public readonly int $postId,
        public readonly int $topicId,
        public readonly int $forumId,
        public readonly int $posterId,
        public readonly string $message,
    ) {}
}
<?php

declare(strict_types=1);

namespace FluxBB\Search\Domain;

/**
 * Search result value object.
 */
class SearchResult
{
    /**
     * @param int $topicId Topic ID
     * @param string $subject Topic subject
     * @param string $poster Username
     * @param int $posterId User ID
     * @param \DateTimeImmutable $postedAt Post timestamp
     * @param string $excerpt Snippet of matching content
     * @param float $rank Relevance rank
     */
    public function __construct(
        public readonly int $topicId,
        public readonly string $subject,
        public readonly string $poster,
        public readonly int $posterId,
        public readonly \DateTimeImmutable $postedAt,
        public readonly string $excerpt = '',
        public readonly float $rank = 0.0,
    ) {}
}
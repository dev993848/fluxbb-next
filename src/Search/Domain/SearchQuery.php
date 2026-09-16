<?php

declare(strict_types=1);

namespace FluxBB\Search\Domain;

/**
 * Search query value object.
 */
class SearchQuery
{
    /**
     * @param string $keywords Search terms
     * @param int $forumId Optional forum ID filter
     * @param int $userId Optional user ID filter
     * @param int $page Page number (1-based)
     * @param int $perPage Results per page
     */
    public function __construct(
        public readonly string $keywords,
        public readonly int $forumId = 0,
        public readonly int $userId = 0,
        public readonly int $page = 1,
        public readonly int $perPage = 20,
    ) {}
}
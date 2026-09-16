<?php

declare(strict_types=1);

namespace FluxBB\Search\Domain;

/**
 * Repository interface for full-text search.
 */
interface SearchRepository
{
    /**
     * Execute a full-text search query.
     *
     * @param SearchQuery $query Search parameters
     * @return array{results: list<SearchResult>, total: int}
     */
    public function search(SearchQuery $query): array;

    /**
     * Index a post for search (manual trigger).
     *
     * @param int $postId Post ID to index
     */
    public function indexPost(int $postId): void;

    /**
     * Rebuild the full search index (for migration/reindex).
     *
     * @return int Number of rows indexed
     */
    public function rebuildIndex(): int;
}
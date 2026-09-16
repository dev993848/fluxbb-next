<?php

declare(strict_types=1);

namespace FluxBB\Forum\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use FluxBB\Forum\Domain\Category;
use FluxBB\Forum\Domain\Forum;
use FluxBB\Forum\Domain\ForumRepository;

/**
 * Doctrine DBAL implementation of the ForumRepository interface.
 *
 * Reads forum and category data from the database using raw SQL queries
 * via Doctrine DBAL. Expects tables named forum_categories and forum_forums
 * (configurable via DB prefix).
 *
 * @see ForumRepository
 */
class DoctrineForumRepository implements ForumRepository
{
    /**
     * @param Connection $connection The Doctrine DBAL connection
     */
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * Load all categories ordered by their display position.
     *
     * @return list<Category> All categories in display order
     */
    public function findAllCategories(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM forum_categories ORDER BY disp_position'
        );

        return array_map(function (array $row): Category {
            /** @var array<string, mixed> $row */
            return new Category(
                id: (int) $row['id'],
                name: (string) $row['cat_name'],
                position: (int) $row['disp_position'],
            );
        }, $rows);
    }

    /**
     * Load all forums belonging to a specific category.
     *
     * @param int $categoryId The category ID
     * @return list<Forum> Forums in the category, ordered by position
     */
    public function findByCategory(int $categoryId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM forum_forums WHERE cat_id = ? ORDER BY disp_position',
            [$categoryId]
        );

        return array_map(fn (array $row): Forum => $this->hydrateForum($row), $rows);
    }

    /**
     * Load the entire forum structure grouped by category.
     *
     * @return array<int, array{category: Category, forums: list<Forum>}>
     */
    public function findAllGroupedByCategory(): array
    {
        $categories = $this->findAllCategories();
        $result = [];

        foreach ($categories as $category) {
            $result[$category->getId()] = [
                'category' => $category,
                'forums' => $this->findByCategory($category->getId()),
            ];
        }

        return $result;
    }

    /**
     * Find a single forum by its ID.
     *
     * @param int $id The forum ID
     * @return Forum|null The forum, or null if not found
     */
    public function findById(int $id): ?Forum
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM forum_forums WHERE id = ?',
            [$id]
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrateForum($row);
    }

    /**
     * Hydrate a Forum entity from a database row.
     *
     * Maps database column names to Forum constructor parameters.
     * Timestamps are converted from Unix timestamps to DateTimeImmutable.
     *
     * @param array<string, mixed> $row The database row (associative)
     * @return Forum The hydrated Forum entity
     */
    private function hydrateForum(array $row): Forum
    {
        return new Forum(
            id: (int) $row['id'],
            name: (string) ($row['forum_name'] ?? ''),
            description: (string) ($row['forum_desc'] ?? ''),
            categoryId: (int) $row['cat_id'],
            position: (int) ($row['disp_position'] ?? 0),
            lastPostId: isset($row['last_post_id']) ? (int) $row['last_post_id'] : null,
            lastPosterId: isset($row['last_poster_id']) ? (int) $row['last_poster_id'] : null,
            lastPosterName: isset($row['last_poster']) ? (string) $row['last_poster'] : null,
            lastPosted: isset($row['last_post'])
                ? new \DateTimeImmutable('@' . $row['last_post'])
                : null,
            numTopics: (int) ($row['num_topics'] ?? 0),
            numPosts: (int) ($row['num_posts'] ?? 0),
            redirectUrl: (string) ($row['redirect_url'] ?? ''),
            moderators: isset($row['moderators']) ? (string) $row['moderators'] : null,
        );
    }
}
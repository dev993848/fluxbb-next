<?php

declare(strict_types=1);

namespace FluxBB\Forum\Domain;

/**
 * Repository interface for the Forum aggregate.
 *
 * Defines the persistence contract for retrieving forums and categories.
 * Implementations typically use Doctrine DBAL or a similar data access layer.
 *
 * @see DoctrineForumRepository
 */
interface ForumRepository
{
    /**
     * Load all categories ordered by their display position.
     *
     * @return list<Category> All categories in display order
     */
    public function findAllCategories(): array;

    /**
     * Load all forums belonging to a specific category.
     *
     * @param int $categoryId The category ID
     * @return list<Forum> Forums in the category, ordered by position
     */
    public function findByCategory(int $categoryId): array;

    /**
     * Load the entire forum structure grouped by category.
     *
     * Result format:
     * [
     *   categoryId => [
     *     'category' => Category,
     *     'forums' => [Forum, ...],
     *   ],
     * ]
     *
     * @return array<int, array{category: Category, forums: list<Forum>}>
     */
    public function findAllGroupedByCategory(): array;

    /**
     * Find a single forum by its ID.
     *
     * @param int $id The forum ID
     * @return Forum|null The forum, or null if not found
     */
    public function findById(int $id): ?Forum;
}
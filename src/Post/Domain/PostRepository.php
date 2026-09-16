<?php

declare(strict_types=1);

namespace FluxBB\Post\Domain;

/**
 * Repository interface for the Post aggregate.
 */
interface PostRepository
{
    public function findById(int $id): ?Post;

    /**
     * @return list<Post> Posts in a topic, ordered by creation time
     */
    public function findByTopic(int $topicId, int $page = 1, int $perPage = 20): array;

    /**
     * @return list<Post> Latest posts in a forum
     */
    public function findLatestByForum(int $forumId, int $limit = 15): array;

    public function countByTopic(int $topicId): int;

    public function save(Post $post): void;
    public function delete(Post $post): void;
}
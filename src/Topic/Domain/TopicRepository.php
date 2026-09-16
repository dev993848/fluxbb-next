<?php

declare(strict_types=1);

namespace FluxBB\Topic\Domain;

/**
 * Repository interface for the Topic aggregate.
 */
interface TopicRepository
{
    /**
     * @return list<Topic> Topics in a forum, ordered by sticky then last post
     */
    public function findByForum(int $forumId): array;

    public function findById(int $id): ?Topic;
    public function save(Topic $topic): void;
    public function delete(Topic $topic): void;

    /**
     * Update topic counters after a post is created or deleted.
     */
    public function updateLastPost(int $topicId, int $postId, int $posterId, string $poster, \DateTimeImmutable $postedAt): void;
}
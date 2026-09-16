<?php

declare(strict_types=1);

namespace FluxBB\Topic\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use FluxBB\Topic\Domain\Topic;
use FluxBB\Topic\Domain\TopicRepository;

/**
 * Doctrine DBAL implementation of the TopicRepository.
 */
class DoctrineTopicRepository implements TopicRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function findByForum(int $forumId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM forum_topics WHERE forum_id = ? ORDER BY sticky DESC, last_post DESC',
            [$forumId]
        );

        return array_map(fn (array $row): Topic => $this->hydrate($row), $rows);
    }

    public function findById(int $id): ?Topic
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM forum_topics WHERE id = ?',
            [$id]
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function save(Topic $topic): void
    {
        $this->connection->insert('forum_topics', [
            'poster' => $topic->getPoster(),
            'poster_id' => $topic->getPosterId(),
            'subject' => $topic->getSubject(),
            'posted' => $topic->getPostedAt()->getTimestamp(),
            'forum_id' => $topic->getForumId(),
            'closed' => $topic->isClosed() ? 1 : 0,
            'sticky' => $topic->isSticky() ? 1 : 0,
        ]);
    }

    public function delete(Topic $topic): void
    {
        $this->connection->delete('forum_topics', ['id' => $topic->getId()]);
    }

    public function updateLastPost(int $topicId, int $postId, int $posterId, string $poster, \DateTimeImmutable $postedAt): void
    {
        $this->connection->update('forum_topics', [
            'last_post_id' => $postId,
            'last_poster_id' => $posterId,
            'last_poster' => $poster,
            'last_post' => $postedAt->getTimestamp(),
        ], ['id' => $topicId]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Topic
    {
        $topic = new Topic(
            id: (int) $row['id'],
            forumId: (int) $row['forum_id'],
            subject: (string) $row['subject'],
            poster: (string) $row['poster'],
            posterId: (int) $row['poster_id'],
            postedAt: new \DateTimeImmutable('@' . $row['posted']),
            firstPostId: (int) ($row['first_post_id'] ?? 0),
            lastPostId: (int) ($row['last_post_id'] ?? 0),
            lastPosterId: (int) ($row['last_poster_id'] ?? 0),
            lastPoster: (string) ($row['last_poster'] ?? ''),
            lastPostedAt: new \DateTimeImmutable('@' . ($row['last_post'] ?? $row['posted'])),
            numReplies: (int) ($row['num_replies'] ?? 0),
            closed: !empty($row['closed']),
            sticky: !empty($row['sticky']),
        );

        return $topic;
    }
}
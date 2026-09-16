<?php

declare(strict_types=1);

namespace FluxBB\Post\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use FluxBB\Post\Domain\Post;
use FluxBB\Post\Domain\PostRepository;

/**
 * Doctrine DBAL implementation of PostRepository.
 */
class DoctrinePostRepository implements PostRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function findById(int $id): ?Post
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM forum_posts WHERE id = ?',
            [$id]
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByTopic(int $topicId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM forum_posts WHERE topic_id = ? ORDER BY posted ASC LIMIT ? OFFSET ?',
            [$topicId, $perPage, $offset]
        );

        return array_map(fn (array $row): Post => $this->hydrate($row), $rows);
    }

    public function findLatestByForum(int $forumId, int $limit = 15): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT p.* FROM forum_posts p
             JOIN forum_topics t ON p.topic_id = t.id
             WHERE t.forum_id = ?
             ORDER BY p.posted DESC LIMIT ?',
            [$forumId, $limit]
        );

        return array_map(fn (array $row): Post => $this->hydrate($row), $rows);
    }

    public function countByTopic(int $topicId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM forum_posts WHERE topic_id = ?',
            [$topicId]
        );
    }

    public function save(Post $post): void
    {
        $data = [
            'poster' => $post->getPoster(),
            'poster_id' => $post->getPosterId(),
            'poster_ip' => $post->getPosterIp() ?? '',
            'message' => $post->getMessage(),
            'hide_smilies' => $post->getHideSmilies() ? 1 : 0,
            'posted' => $post->getPostedAt()->getTimestamp(),
            'topic_id' => $post->getTopicId(),
        ];

        if ($post->identity() === 0) {
            $this->connection->insert('forum_posts', $data);
        } else {
            $data['edited'] = time();
            $data['edited_by'] = $post->getEditedBy() ?? '';
            $this->connection->update('forum_posts', $data, ['id' => $post->identity()]);
        }
    }

    public function delete(Post $post): void
    {
        $this->connection->delete('forum_posts', ['id' => $post->identity()]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Post
    {
        return new Post(
            id: (int) $row['id'],
            topicId: (int) $row['topic_id'],
            forumId: (int) ($row['forum_id'] ?? 0),
            poster: (string) $row['poster'],
            posterId: (int) $row['poster_id'],
            message: (string) $row['message'],
            postedAt: new \DateTimeImmutable('@' . $row['posted']),
            posterIp: isset($row['poster_ip']) ? (string) $row['poster_ip'] : null,
            hideSmilies: !empty($row['hide_smilies']),
            editedAt: isset($row['edited']) ? new \DateTimeImmutable('@' . $row['edited']) : null,
            editedBy: isset($row['edited_by']) ? (int) $row['edited_by'] : null,
        );
    }
}
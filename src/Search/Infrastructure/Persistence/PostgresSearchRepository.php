<?php

declare(strict_types=1);

namespace FluxBB\Search\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use FluxBB\Search\Domain\SearchQuery;
use FluxBB\Search\Domain\SearchRepository;
use FluxBB\Search\Domain\SearchResult;

/**
 * PostgreSQL full-text search implementation using tsvector.
 *
 * Uses PostgreSQL's built-in text search with GIN indexes on
 * forum_posts.search_vector and forum_topics.search_vector.
 */
class PostgresSearchRepository implements SearchRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function search(SearchQuery $query): array
    {
        $keywords = $query->keywords;
        $offset = ($query->page - 1) * $query->perPage;

        // Sanitize search input for tsquery
        $tsQuery = $this->buildTsQuery($keywords);

        if ($tsQuery === '') {
            return ['results' => [], 'total' => 0];
        }

        // Build WHERE clauses
        $conditions = ['p.search_vector @@ to_tsquery(\'english\', :query)'];
        $params = ['query' => $tsQuery];
        $types = [];

        if ($query->forumId > 0) {
            $conditions[] = 't.forum_id = :forum_id';
            $params['forum_id'] = $query->forumId;
        }

        if ($query->userId > 0) {
            $conditions[] = 'p.poster_id = :user_id';
            $params['user_id'] = $query->userId;
        }

        $where = implode(' AND ', $conditions);

        // Count total results
        $countSql = "SELECT COUNT(*) FROM forum_posts p
            JOIN forum_topics t ON p.topic_id = t.id
            WHERE {$where}";

        $total = (int) $this->connection->fetchOne($countSql, $params, $types);

        // Get ranked results
        $sql = "SELECT
            t.id AS topic_id,
            t.subject,
            p.poster,
            p.poster_id,
            p.posted,
            ts_headline('english', p.message, to_tsquery('english', :query),
                'MaxWords=40, MinWords=20, StartSel=<mark>, StopSel=</mark>') AS excerpt,
            ts_rank(p.search_vector, to_tsquery('english', :query)) AS rank
            FROM forum_posts p
            JOIN forum_topics t ON p.topic_id = t.id
            WHERE {$where}
            ORDER BY rank DESC
            LIMIT :limit OFFSET :offset";

        $params['limit'] = $query->perPage;
        $params['offset'] = $offset;

        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);

        $results = [];
        foreach ($rows as $row) {
            $results[] = new SearchResult(
                topicId: (int) $row['topic_id'],
                subject: (string) $row['subject'],
                poster: (string) $row['poster'],
                posterId: (int) $row['poster_id'],
                postedAt: new \DateTimeImmutable('@' . $row['posted']),
                excerpt: (string) ($row['excerpt'] ?? ''),
                rank: (float) ($row['rank'] ?? 0.0),
            );
        }

        return ['results' => $results, 'total' => $total];
    }

    public function indexPost(int $postId): void
    {
        $this->connection->executeStatement(
            "UPDATE forum_posts SET search_vector = to_tsvector('english', COALESCE(message, '')) WHERE id = ?",
            [$postId]
        );
    }

    public function rebuildIndex(): int
    {
        $posts = $this->connection->executeStatement(
            "UPDATE forum_posts SET search_vector = to_tsvector('english', COALESCE(message, ''))"
        );

        $this->connection->executeStatement(
            "UPDATE forum_topics SET search_vector = to_tsvector('english', COALESCE(subject, ''))"
        );

        return (int) $posts;
    }

    /**
     * Build a tsquery string from user keywords.
     *
     * Handles:
     * - Multiple words (AND)
     * - Quoted phrases (exact match)
     * - Special characters sanitization
     *
     * @param string $keywords Raw search input
     * @return string Sanitized tsquery
     */
    private function buildTsQuery(string $keywords): string
    {
        $keywords = trim($keywords);
        if ($keywords === '') {
            return '';
        }

        // Remove special characters that break tsquery
        $keywords = preg_replace('/[^\w\s"-]+/u', ' ', $keywords) ?? '';
        $keywords = preg_replace('/\s+/', ' ', $keywords) ?? '';
        $keywords = trim($keywords);

        if ($keywords === '') {
            return '';
        }

        // Support quoted phrases
        $parts = [];
        if (preg_match_all('/"([^"]+)"|(\S+)/u', $keywords, $matches)) {
            foreach ($matches[0] as $match) {
                $clean = trim($match, '"');
                if ($clean !== '') {
                    // Escape special tsquery characters
                    $clean = str_replace(['&', '|', '!', '(', ')', ':'], ' ', $clean);
                    $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');
                    if ($clean !== '') {
                        $parts[] = $clean;
                    }
                }
            }
        }

        if ($parts === []) {
            return '';
        }

        // Join with AND for relevance
        return implode(' & ', array_unique($parts));
    }
}
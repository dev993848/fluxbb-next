<?php

declare(strict_types=1);

namespace FluxBB\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use FluxBB\Post\Domain\Post;
use FluxBB\Post\Infrastructure\Persistence\DoctrinePostRepository;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for DoctrinePostRepository.
 *
 * Requires a running PostgreSQL instance (via docker-compose).
 * Skips if no DB connection is available.
 */
class DoctrinePostRepositoryTest extends TestCase
{
    private static ?Connection $connection = null;
    private DoctrinePostRepository $repository;

    public static function setUpBeforeClass(): void
    {
        try {
            self::$connection = DriverManager::getConnection([
                'driver'   => 'pdo_pgsql',
                'host'     => $_ENV['DB_HOST'] ?? '127.0.0.1',
                'port'     => $_ENV['DB_PORT'] ?? '5432',
                'dbname'   => $_ENV['DB_NAME'] ?? 'fluxbb',
                'user'     => $_ENV['DB_USER'] ?? 'fluxbb',
                'password' => $_ENV['DB_PASSWORD'] ?? 'fluxbb',
            ]);
            self::$connection->connect();
        } catch (\Throwable $e) {
            self::$connection = null;
        }
    }

    protected function setUp(): void
    {
        if (self::$connection === null) {
            $this->markTestSkipped('No database connection available.');
        }
        $this->repository = new DoctrinePostRepository(self::$connection);
    }

    public function testFindByIdReturnsNullForUnknown(): void
    {
        $post = $this->repository->findById(999999);
        $this->assertNull($post);
    }

    public function testSaveAndFindById(): void
    {
        $post = new Post(
            id: 0,
            topicId: 1,
            forumId: 1,
            posterId: 1,
            poster: 'TestUser',
            message: 'Integration test post',
            postedAt: new \DateTimeImmutable(),
            hideSmilies: false,
            posterIp: '127.0.0.1',
        );

        $this->repository->save($post);
        $this->assertGreaterThan(0, $post->identity(), 'Post should have an ID after save');

        $found = $this->repository->findById($post->identity());
        $this->assertNotNull($found);
        $this->assertSame('TestUser', $found->getPoster());
        $this->assertSame('Integration test post', $found->getMessage());
    }

    public function testCountByTopic(): void
    {
        $count = $this->repository->countByTopic(1);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testFindByTopicReturnsPosts(): void
    {
        $posts = $this->repository->findByTopic(1);
        $this->assertIsArray($posts);
    }

    public function testFindLatestByForumReturnsPosts(): void
    {
        $posts = $this->repository->findLatestByForum(1, 5);
        $this->assertIsArray($posts);
        $this->assertCount(5, $posts);
    }
}
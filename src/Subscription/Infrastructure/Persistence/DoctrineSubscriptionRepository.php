<?php

declare(strict_types=1);

namespace FluxBB\Subscription\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use FluxBB\Subscription\Domain\SubscriptionRepository;
use FluxBB\Subscription\Domain\TopicSubscription;
use FluxBB\User\Domain\UserId;

/**
 * Doctrine DBAL implementation of SubscriptionRepository.
 *
 * Reads and writes topic subscriptions from the forum_topic_subscriptions table.
 */
class DoctrineSubscriptionRepository implements SubscriptionRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function findByUserAndTopic(UserId $userId, int $topicId): ?TopicSubscription
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM forum_topic_subscriptions WHERE user_id = ? AND topic_id = ?',
            [$userId->toInt(), $topicId]
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByTopic(int $topicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM forum_topic_subscriptions WHERE topic_id = ?',
            [$topicId]
        );

        return array_map(fn (array $row): TopicSubscription => $this->hydrate($row), $rows);
    }

    public function findByUser(UserId $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM forum_topic_subscriptions WHERE user_id = ?',
            [$userId->toInt()]
        );

        return array_map(fn (array $row): TopicSubscription => $this->hydrate($row), $rows);
    }

    public function save(TopicSubscription $subscription): void
    {
        $this->connection->insert('forum_topic_subscriptions', [
            'user_id' => $subscription->getUserId()->toInt(),
            'topic_id' => $subscription->getTopicId(),
            'subscribed_at' => $subscription->getSubscribedAt()->getTimestamp(),
        ]);
    }

    public function delete(TopicSubscription $subscription): void
    {
        $this->connection->delete('forum_topic_subscriptions', [
            'user_id' => $subscription->getUserId()->toInt(),
            'topic_id' => $subscription->getTopicId(),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): TopicSubscription
    {
        return new TopicSubscription(
            id: (int) $row['id'],
            userId: new UserId((int) $row['user_id']),
            topicId: (int) $row['topic_id'],
            subscribedAt: new \DateTimeImmutable('@' . ($row['subscribed_at'] ?? time())),
        );
    }
}
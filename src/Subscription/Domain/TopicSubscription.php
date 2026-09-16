<?php

declare(strict_types=1);

namespace FluxBB\Subscription\Domain;

use FluxBB\Shared\Domain\AggregateRoot;
use FluxBB\User\Domain\UserId;

/**
 * Topic subscription.
 *
 * Represents a user subscribing to a topic to receive notifications
 * when new posts are made.
 *
 * Domain scars:
 * - Users can subscribe to topics when posting (checkbox in post.php)
 * - Users receive email notifications when a new reply is posted
 * - Users can manage subscriptions from their profile
 *
 * @see https://github.com/fluxbb/fluxbb/issues/82
 * @see https://github.com/fluxbb/fluxbb/issues/96
 */
class TopicSubscription extends AggregateRoot
{
    /**
     * @param int                $id           Unique subscription identifier
     * @param UserId             $userId       The subscribing user
     * @param int                $topicId      The topic being subscribed to
     * @param \DateTimeImmutable $subscribedAt When the subscription was created
     */
    public function __construct(
        private readonly int $id,
        private readonly UserId $userId,
        private readonly int $topicId,
        private readonly \DateTimeImmutable $subscribedAt = new \DateTimeImmutable(),
    ) {}

    public function identity(): int
    {
        return $this->id;
    }

    public function getId(): int { return $this->id; }
    public function getUserId(): UserId { return $this->userId; }
    public function getTopicId(): int { return $this->topicId; }
    public function getSubscribedAt(): \DateTimeImmutable { return $this->subscribedAt; }
}

/**
 * Domain event: a user subscribed to a topic.
 */
class TopicSubscribedEvent implements \FluxBB\Shared\Domain\DomainEvent
{
    public function __construct(
        public readonly UserId $userId,
        public readonly int $topicId,
        private readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function aggregateId(): UserId { return $this->userId; }
}

/**
 * Domain event: a user unsubscribed from a topic.
 */
class TopicUnsubscribedEvent implements \FluxBB\Shared\Domain\DomainEvent
{
    public function __construct(
        public readonly UserId $userId,
        public readonly int $topicId,
        private readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function aggregateId(): UserId { return $this->userId; }
}

/**
 * Repository interface for TopicSubscription.
 */
interface SubscriptionRepository
{
    public function findByUserAndTopic(UserId $userId, int $topicId): ?TopicSubscription;

    /** @return list<TopicSubscription> */
    public function findByTopic(int $topicId): array;

    /** @return list<TopicSubscription> */
    public function findByUser(UserId $userId): array;

    public function save(TopicSubscription $subscription): void;
    public function delete(TopicSubscription $subscription): void;
}
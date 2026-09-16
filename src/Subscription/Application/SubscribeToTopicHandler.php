<?php

declare(strict_types=1);

namespace FluxBB\Subscription\Application;

use FluxBB\Subscription\Domain\SubscriptionRepository;
use FluxBB\Subscription\Domain\TopicSubscription;
use FluxBB\Subscription\Domain\TopicSubscribedEvent;
use FluxBB\User\Domain\UserId;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Handler for subscribing a user to a topic.
 */
class SubscribeToTopicHandler
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function handle(SubscribeToTopicCommand $command): TopicSubscription
    {
        // Check if already subscribed
        $existing = $this->subscriptionRepository->findByUserAndTopic($command->userId, $command->topicId);
        if ($existing !== null) {
            throw new \DomainException('Already subscribed to this topic.');
        }

        $subscription = new TopicSubscription(
            id: 0,
            userId: $command->userId,
            topicId: $command->topicId,
        );

        $this->subscriptionRepository->save($subscription);
        $this->eventDispatcher->dispatch(new TopicSubscribedEvent($command->userId, $command->topicId));

        return $subscription;
    }
}
<?php

declare(strict_types=1);

namespace FluxBB\Subscription\Application;

use FluxBB\Subscription\Domain\SubscriptionRepository;
use FluxBB\Subscription\Domain\TopicUnsubscribedEvent;
use FluxBB\User\Domain\UserId;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Handler for unsubscribing a user from a topic.
 */
class UnsubscribeFromTopicHandler
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function handle(UnsubscribeFromTopicCommand $command): void
    {
        $subscription = $this->subscriptionRepository->findByUserAndTopic($command->userId, $command->topicId);
        if ($subscription === null) {
            throw new \DomainException('Not subscribed to this topic.');
        }

        $this->subscriptionRepository->delete($subscription);
        $this->eventDispatcher->dispatch(new TopicUnsubscribedEvent($command->userId, $command->topicId));
    }
}
<?php

declare(strict_types=1);

namespace FluxBB\Subscription\Infrastructure\Controller;

use FluxBB\Subscription\Application\SubscribeToTopicCommand;
use FluxBB\Subscription\Application\SubscribeToTopicHandler;
use FluxBB\Subscription\Application\UnsubscribeFromTopicCommand;
use FluxBB\Subscription\Application\UnsubscribeFromTopicHandler;
use FluxBB\User\Domain\UserId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for subscription management.
 */
class SubscriptionController
{
    public function __construct(
        private readonly SubscribeToTopicHandler $subscribeHandler,
        private readonly UnsubscribeFromTopicHandler $unsubscribeHandler,
    ) {}

    /**
     * Subscribe the current user to a topic.
     *
     * @param Request $request The incoming HTTP request
     * @param array<string, scalar> $params Route parameters (expects 'topicId', 'userId')
     * @return Response Subscription confirmation or error
     */
    public function subscribe(Request $request, array $params): Response
    {
        $topicId = (int) ($params['topicId'] ?? 0);
        $userId = (int) ($params['userId'] ?? 0);

        try {
            $this->subscribeHandler->handle(new SubscribeToTopicCommand(
                userId: new UserId($userId),
                topicId: $topicId,
            ));

            return new Response('Subscribed successfully.');
        } catch (\DomainException $e) {
            return new Response($e->getMessage(), 400);
        }
    }

    /**
     * Unsubscribe the current user from a topic.
     *
     * @param Request $request The incoming HTTP request
     * @param array<string, scalar> $params Route parameters (expects 'topicId', 'userId')
     * @return Response Unsubscription confirmation or error
     */
    public function unsubscribe(Request $request, array $params): Response
    {
        $topicId = (int) ($params['topicId'] ?? 0);
        $userId = (int) ($params['userId'] ?? 0);

        try {
            $this->unsubscribeHandler->handle(new UnsubscribeFromTopicCommand(
                userId: new UserId($userId),
                topicId: $topicId,
            ));

            return new Response('Unsubscribed successfully.');
        } catch (\DomainException $e) {
            return new Response($e->getMessage(), 400);
        }
    }
}
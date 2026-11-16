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
 *
 * User identity always comes from the session — never trust userId from the URL alone.
 */
class SubscriptionController
{
    public function __construct(
        private readonly SubscribeToTopicHandler $subscribeHandler,
        private readonly UnsubscribeFromTopicHandler $unsubscribeHandler,
    ) {}

    /**
     * Subscribe the current session user to a topic (POST).
     *
     * @param array<string, scalar> $params Route parameters (expects 'topicId')
     */
    public function subscribe(Request $request, array $params): Response
    {
        $userId = $this->requireSessionUserId($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        $topicId = (int) ($params['topicId'] ?? 0);

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
     * Unsubscribe the current session user from a topic (POST).
     *
     * @param array<string, scalar> $params Route parameters (expects 'topicId')
     */
    public function unsubscribe(Request $request, array $params): Response
    {
        $userId = $this->requireSessionUserId($request);
        if ($userId instanceof Response) {
            return $userId;
        }

        return $this->doUnsubscribe($userId, (int) ($params['topicId'] ?? 0));
    }

    /**
     * Email unsubscribe link: GET with userId, but only if it matches the session user.
     *
     * @param array<string, scalar> $params Route parameters (expects 'topicId', 'userId')
     */
    public function unsubscribeLink(Request $request, array $params): Response
    {
        $sessionUserId = $this->requireSessionUserId($request);
        if ($sessionUserId instanceof Response) {
            return $sessionUserId;
        }

        $pathUserId = (int) ($params['userId'] ?? 0);
        if ($pathUserId !== $sessionUserId) {
            return new Response('Forbidden', 403);
        }

        return $this->doUnsubscribe($sessionUserId, (int) ($params['topicId'] ?? 0));
    }

    private function doUnsubscribe(int $userId, int $topicId): Response
    {
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

    /**
     * @return int|Response
     */
    private function requireSessionUserId(Request $request): int|Response
    {
        $session = $request->getSession();
        if (!$session->has('user_id')) {
            return new Response('', 302, ['Location' => '/login']);
        }

        $raw = $session->get('user_id');
        if (is_int($raw)) {
            return $raw;
        }
        if (is_numeric($raw)) {
            return (int) $raw;
        }

        return new Response('', 302, ['Location' => '/login']);
    }
}

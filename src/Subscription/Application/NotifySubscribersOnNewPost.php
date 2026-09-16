<?php

declare(strict_types=1);

namespace FluxBB\Subscription\Application;

use FluxBB\Post\Domain\PostCreated;
use FluxBB\Shared\Infrastructure\Mail\FluxBBMailer;
use FluxBB\Subscription\Domain\SubscriptionRepository;
use FluxBB\Topic\Domain\TopicRepository;
use FluxBB\User\Domain\UserRepository;

/**
 * Event subscriber: notify topic subscribers when a new post is created.
 *
 * Sends real email via FluxBBMailer (supports Symfony Mailer with native fallback).
 *
 * @see https://github.com/fluxbb/fluxbb/issues/82
 * @see https://github.com/fluxbb/fluxbb/issues/96
 */
class NotifySubscribersOnNewPost
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly UserRepository $users,
        private readonly TopicRepository $topics,
        private readonly FluxBBMailer $mailer,
    ) {}

    /**
     * Handle PostCreated event.
     *
     * Finds all subscribers of the topic and sends an email notification
     * to each subscriber (except the poster themselves).
     */
    public function __invoke(PostCreated $event): void
    {
        $topic = $this->topics->findById($event->topicId);
        if ($topic === null) {
            return;
        }

        $subscribers = $this->subscriptions->findByTopic($event->topicId);

        foreach ($subscribers as $subscription) {
            // Skip the post author — they already know they replied
            if ($subscription->getUserId()->toInt() === $event->posterId) {
                continue;
            }

            $user = $this->users->findById($subscription->getUserId());
            if ($user === null) {
                continue;
            }

            $subject = sprintf('New reply: %s', $topic->getSubject());
            $body = sprintf(
                "<h2>New reply in \"%s\"</h2>"
                . "<p>A new reply has been posted in a topic you're subscribed to.</p>"
                . "<p><a href=\"%s/topic/%d\">View the topic</a></p>"
                . "<hr><p style=\"color:#888;\">You are receiving this because you subscribed to this topic."
                . " <a href=\"%s/topic/%d/unsubscribe/%d\">Unsubscribe</a></p>",
                htmlspecialchars($topic->getSubject()),
                'https://your-forum.com',
                $event->topicId,
                'https://your-forum.com',
                $event->topicId,
                $user->getId()->toInt()
            );
            $altBody = sprintf(
                "New reply in \"%s\"\n\n"
                . "A new reply has been posted in a topic you're subscribed to.\n\n"
                . "View: https://your-forum.com/topic/%d\n\n"
                . "Unsubscribe: https://your-forum.com/topic/%d/unsubscribe/%d",
                $topic->getSubject(),
                $event->topicId,
                $event->topicId,
                $user->getId()->toInt()
            );

            $this->mailer->send(
                $user->getEmail()->toString(),
                $subject,
                $body,
                $altBody
            );
        }
    }
}
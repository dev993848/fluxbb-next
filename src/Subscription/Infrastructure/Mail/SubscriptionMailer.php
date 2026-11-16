<?php

declare(strict_types=1);

namespace FluxBB\Subscription\Infrastructure\Mail;

use FluxBB\Shared\Infrastructure\Mail\FluxBBMailer;
use FluxBB\Subscription\Domain\TopicSubscription;

/**
 * Thin adapter kept for BC — delegates to FluxBBMailer.
 *
 * @deprecated Use FluxBBMailer / NotifySubscribersOnNewPost instead.
 */
class SubscriptionMailer
{
    public function __construct(
        private readonly FluxBBMailer $mailer,
        private readonly string $appUrl = '',
    ) {}

    /**
     * Send a notification about a new reply.
     */
    public function sendNewReplyNotification(
        TopicSubscription $subscription,
        string $toEmail,
        string $topicSubject,
        int $topicId,
    ): void {
        $baseUrl = rtrim($this->appUrl !== '' ? $this->appUrl : (string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/');
        $subject = sprintf('New reply: %s', $topicSubject);
        $body = sprintf(
            "<p>There's a new reply in the topic \"%s\".</p><p><a href=\"%s/topic/%d\">View</a></p>",
            htmlspecialchars($topicSubject),
            htmlspecialchars($baseUrl),
            $topicId
        );
        $altBody = sprintf(
            "There's a new reply in the topic \"%s\".\nView: %s/topic/%d",
            $topicSubject,
            $baseUrl,
            $topicId
        );

        $this->mailer->send($toEmail, $subject, $body, $altBody);
    }
}

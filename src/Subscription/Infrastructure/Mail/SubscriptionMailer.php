<?php

declare(strict_types=1);

namespace FluxBB\Subscription\Infrastructure\Mail;

use FluxBB\Subscription\Domain\TopicSubscription;

/**
 * Email notification service for topic subscriptions.
 *
 * Sends HTML email notifications to subscribers when a new reply is posted.
 * In production, this would use Symfony Mailer.
 */
class SubscriptionMailer
{
    /** @param string $fromEmail Sender email address */
    public function __construct(
        private readonly string $fromEmail = 'noreply@fluxbb.local',
    ) {}

    /**
     * Send a notification about a new reply.
     *
     * @param TopicSubscription $subscription The subscription
     * @param string $toEmail Recipient email
     * @param string $topicSubject Topic subject
     * @param int $topicId Topic ID for link
     */
    public function sendNewReplyNotification(
        TopicSubscription $subscription,
        string $toEmail,
        string $topicSubject,
        int $topicId,
    ): void {
        $subject = sprintf('New reply: %s', $topicSubject);
        $body = sprintf(
            "Hello!\n\nThere's a new reply in the topic \"%s\" that you're subscribed to.\n\n" .
            "View it here: %s/topic/%d\n\n--\nFluxBB Forum",
            $topicSubject,
            'https://your-forum.com',
            $topicId
        );

        // TODO: Use Symfony Mailer in production
        error_log(sprintf('[FluxBB Mail] To: %s, Subject: %s', $toEmail, $subject));
    }
}
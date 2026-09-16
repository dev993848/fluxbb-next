<?php

declare(strict_types=1);

namespace FluxBB\Subscription\Application;

use FluxBB\User\Domain\UserId;

/**
 * Command: subscribe a user to a topic.
 */
class SubscribeToTopicCommand
{
    public function __construct(
        public readonly UserId $userId,
        public readonly int $topicId,
    ) {}
}
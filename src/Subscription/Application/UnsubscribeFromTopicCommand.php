<?php

declare(strict_types=1);

namespace FluxBB\Subscription\Application;

use FluxBB\User\Domain\UserId;

/**
 * Command: unsubscribe a user from a topic.
 */
class UnsubscribeFromTopicCommand
{
    public function __construct(
        public readonly UserId $userId,
        public readonly int $topicId,
    ) {}
}
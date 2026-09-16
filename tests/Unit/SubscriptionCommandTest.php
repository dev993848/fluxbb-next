<?php

declare(strict_types=1);

namespace FluxBB\Tests\Unit;

use FluxBB\Subscription\Application\SubscribeToTopicCommand;
use FluxBB\Subscription\Application\UnsubscribeFromTopicCommand;
use FluxBB\User\Domain\UserId;
use PHPUnit\Framework\TestCase;

/**
 * Tests for subscription commands.
 */
class SubscriptionCommandTest extends TestCase
{
    public function testSubscribeCommandCanBeCreated(): void
    {
        $command = new SubscribeToTopicCommand(
            userId: new UserId(42),
            topicId: 7,
        );

        $this->assertSame(42, $command->userId->toInt());
        $this->assertSame(7, $command->topicId);
    }

    public function testUnsubscribeCommandCanBeCreated(): void
    {
        $command = new UnsubscribeFromTopicCommand(
            userId: new UserId(42),
            topicId: 7,
        );

        $this->assertSame(42, $command->userId->toInt());
        $this->assertSame(7, $command->topicId);
    }
}
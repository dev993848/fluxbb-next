<?php

declare(strict_types=1);

namespace FluxBB\Tests\Unit;

use FluxBB\Topic\Domain\TopicStateChanged;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Topic domain events.
 */
class TopicDomainEventTest extends TestCase
{
    public function testStateChangedEventCanBeCreated(): void
    {
        $event = new TopicStateChanged(
            topicId: 42,
            newState: 'closed',
        );

        $this->assertSame(42, $event->topicId);
        $this->assertSame('closed', $event->newState);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredAt());
    }
}
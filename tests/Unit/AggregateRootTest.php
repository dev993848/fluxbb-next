<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class AggregateRootTest extends TestCase
{
    public function test_release_events_returns_recorded_events(): void
    {
        $aggregate = new TestAggregate(1);
        $aggregate->doSomething();
        $events = $aggregate->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertEquals('something happened', $events[0]->message);
    }

    public function test_release_events_clears_events(): void
    {
        $aggregate = new TestAggregate(1);
        $aggregate->doSomething();
        $aggregate->releaseEvents();
        $eventsAfter = $aggregate->releaseEvents();

        $this->assertCount(0, $eventsAfter);
    }

    public function test_clear_events_without_releasing(): void
    {
        $aggregate = new TestAggregate(1);
        $aggregate->doSomething();
        $aggregate->callClearEvents();
        $events = $aggregate->releaseEvents();

        $this->assertCount(0, $events);
    }
}
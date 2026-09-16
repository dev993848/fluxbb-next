<?php

declare(strict_types=1);

use FluxBB\Shared\Infrastructure\Bus\SimpleEventDispatcher;
use PHPUnit\Framework\TestCase;

class SimpleEventDispatcherTest extends TestCase
{
    public function test_dispatch_calls_listeners(): void
    {
        $dispatcher = new SimpleEventDispatcher();
        $called = false;

        $dispatcher->addListener(TestEvent::class, function (TestEvent $event) use (&$called): void {
            $called = true;
            $this->assertEquals('hello', $event->message);
        });

        $dispatcher->dispatch(new TestEvent('hello'));

        $this->assertTrue($called);
    }

    public function test_dispatch_multiple_listeners(): void
    {
        $dispatcher = new SimpleEventDispatcher();
        $count = 0;

        $dispatcher->addListener(TestEvent::class, function () use (&$count): void {
            $count++;
        });
        $dispatcher->addListener(TestEvent::class, function () use (&$count): void {
            $count++;
        });

        $dispatcher->dispatch(new TestEvent('test'));

        $this->assertEquals(2, $count);
    }

    public function test_multiple_different_events(): void
    {
        $dispatcher = new SimpleEventDispatcher();
        $events = [];

        $dispatcher->addListener(TestEvent::class, function (TestEvent $e) use (&$events): void {
            $events[] = $e->message;
        });

        $dispatcher->dispatch(new TestEvent('first'));
        $dispatcher->dispatch(new TestEvent('second'));

        $this->assertCount(2, $events);
        $this->assertEquals(['first', 'second'], $events);
    }

    public function test_no_listeners_does_not_error(): void
    {
        $dispatcher = new SimpleEventDispatcher();
        $result = $dispatcher->dispatch(new TestEvent('no listeners'));

        $this->assertInstanceOf(TestEvent::class, $result);
    }
}
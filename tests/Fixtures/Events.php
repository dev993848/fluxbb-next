<?php

declare(strict_types=1);

/**
 * Test fixtures for FluxBB Next unit tests.
 */

// A simple test event used by multiple test cases
class TestEvent
{
    public function __construct(public readonly string $message) {}
}

// A concrete aggregate root for testing
class TestAggregate extends \FluxBB\Shared\Domain\AggregateRoot
{
    public function __construct(private readonly int $id = 1) {}

    public function identity(): int
    {
        return $this->id;
    }

    public function doSomething(): void
    {
        $this->recordEvent(new TestEvent('something happened'));
    }

    public function callClearEvents(): void
    {
        $this->clearEvents();
    }
}
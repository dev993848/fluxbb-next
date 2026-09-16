<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Time;

use Psr\Clock\ClockInterface;

/**
 * System clock implementation of PSR-20 ClockInterface.
 *
 * Provides the current time based on the system clock. This is the default
 * implementation used in production. For testing, consider creating a
 * mock/frozen clock that implements ClockInterface and returns a fixed time.
 */
class SystemClock implements ClockInterface
{
    /**
     * Get the current date and time as an immutable DateTime object.
     *
     * Uses the default system timezone as configured in PHP.
     *
     * @return \DateTimeImmutable The current date and time
     */
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    /**
     * Get the current date and time in UTC.
     *
     * @return \DateTimeImmutable The current UTC date and time
     */
    public function nowUtc(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Get the current Unix timestamp.
     *
     * @return int The current Unix timestamp
     */
    public function timestamp(): int
    {
        return time();
    }
}
<?php

declare(strict_types=1);

use FluxBB\Moderation\Domain\FloodControl;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the FloodControl specification (domain scar).
 *
 * Covers:
 * - User cannot flood if time since last post < flood interval
 * - User can post after flood interval has passed
 * - Admin/Mod is exempt from flood control
 * - First post (no last_post) is always allowed
 *
 * @see https://github.com/fluxbb/fluxbb/issues/93
 */
class FloodControlTest extends TestCase
{
    private FloodControl $floodControl;

    protected function setUp(): void
    {
        $this->floodControl = new FloodControl();
    }

    public function test_blocks_fast_posting(): void
    {
        $now = time();
        $this->assertFalse($this->floodControl->isAllowed(
            lastPostTimestamp: $now - 10,   // 10 seconds ago
            floodInterval: 30,              // 30 second cooldown
            isAdmmod: false,
            now: $now,
        ));
    }

    public function test_allows_after_flood_interval(): void
    {
        $now = time();
        $this->assertTrue($this->floodControl->isAllowed(
            lastPostTimestamp: $now - 60,   // 60 seconds ago
            floodInterval: 30,              // 30 second cooldown
            isAdmmod: false,
            now: $now,
        ));
    }

    public function test_allows_admin_always(): void
    {
        $now = time();
        $this->assertTrue($this->floodControl->isAllowed(
            lastPostTimestamp: $now - 1,    // 1 second ago
            floodInterval: 30,
            isAdmmod: true,                 // is admin/mod
            now: $now,
        ));
    }

    public function test_allows_first_post(): void
    {
        $now = time();
        $this->assertTrue($this->floodControl->isAllowed(
            lastPostTimestamp: 0,           // never posted
            floodInterval: 30,
            isAdmmod: false,
            now: $now,
        ));
    }

    public function test_remaining_cooldown_is_accurate(): void
    {
        $now = time();
        $remaining = $this->floodControl->remainingCooldown(
            lastPostTimestamp: $now - 10,
            floodInterval: 30,
            now: $now,
        );

        $this->assertEquals(20, $remaining);
    }

    public function test_remaining_cooldown_no_flood(): void
    {
        $now = time();
        $remaining = $this->floodControl->remainingCooldown(
            lastPostTimestamp: $now - 60,
            floodInterval: 30,
            now: $now,
        );

        $this->assertEquals(0, $remaining);
    }
}
<?php

declare(strict_types=1);

use FluxBB\Moderation\Domain\CanEditPost;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the CanEditPost specification (domain scar).
 *
 * Covers:
 * - Owner can edit own post within timeout
 * - Owner cannot edit own post after timeout
 * - Owner cannot edit if g_edit_posts = 0
 * - Admin/Mod can edit any post at any time
 * - Mod cannot edit admin's posts
 * - Cannot edit closed topic (unless admin/mod)
 *
 * @see https://github.com/fluxbb/fluxbb/issues/44
 * @see https://github.com/fluxbb/fluxbb/issues/105
 */
class CanEditPostTest extends TestCase
{
    private CanEditPost $specification;

    protected function setUp(): void
    {
        $this->specification = new CanEditPost();
    }

    public function test_owner_can_edit_within_timeout(): void
    {
        $now = time();
        $this->assertTrue($this->specification->isSatisfiedBy(
            posterId: 1,
            currentUserId: 1,
            postTimestamp: $now - 60,      // posted 60 seconds ago
            editTimeout: 600,              // 10 minute timeout
            hasEditPerm: true,
            isAdmmod: false,
            isAdmin: false,
            posterIsAdmin: false,
            topicClosed: false,
            now: $now,
        ));
    }

    public function test_owner_cannot_edit_after_timeout(): void
    {
        $now = time();
        $this->assertFalse($this->specification->isSatisfiedBy(
            posterId: 1,
            currentUserId: 1,
            postTimestamp: $now - 3600,    // posted 1 hour ago
            editTimeout: 600,              // 10 minute timeout
            hasEditPerm: true,
            isAdmmod: false,
            isAdmin: false,
            posterIsAdmin: false,
            topicClosed: false,
            now: $now,
        ));
    }

    public function test_owner_cannot_edit_without_permission(): void
    {
        $now = time();
        $this->assertFalse($this->specification->isSatisfiedBy(
            posterId: 1,
            currentUserId: 1,
            postTimestamp: $now - 60,
            editTimeout: 600,
            hasEditPerm: false,            // g_edit_posts = 0
            isAdmmod: false,
            isAdmin: false,
            posterIsAdmin: false,
            topicClosed: false,
            now: $now,
        ));
    }

    public function test_admin_can_edit_any_post(): void
    {
        $now = time();
        $this->assertTrue($this->specification->isSatisfiedBy(
            posterId: 2,
            currentUserId: 1,              // admin
            postTimestamp: $now - 86400,   // posted 24 hours ago
            editTimeout: 600,
            hasEditPerm: false,
            isAdmmod: true,
            isAdmin: true,
            posterIsAdmin: false,
            topicClosed: true,             // even if topic is closed
            now: $now,
        ));
    }

    public function test_mod_cannot_edit_admin_post(): void
    {
        $now = time();
        $this->assertFalse($this->specification->isSatisfiedBy(
            posterId: 1,
            currentUserId: 2,              // moderator
            postTimestamp: $now - 60,
            editTimeout: 600,
            hasEditPerm: false,
            isAdmmod: true,
            isAdmin: false,
            posterIsAdmin: true,           // post author is admin
            topicClosed: false,
            now: $now,
        ));
    }

    public function test_cannot_edit_other_users_post(): void
    {
        $now = time();
        $this->assertFalse($this->specification->isSatisfiedBy(
            posterId: 1,
            currentUserId: 3,              // different user
            postTimestamp: $now - 60,
            editTimeout: 600,
            hasEditPerm: true,
            isAdmmod: false,
            isAdmin: false,
            posterIsAdmin: false,
            topicClosed: false,
            now: $now,
        ));
    }

    public function test_no_timeout_means_no_restriction(): void
    {
        $now = time();
        $this->assertTrue($this->specification->isSatisfiedBy(
            posterId: 1,
            currentUserId: 1,
            postTimestamp: $now - 999999,  // very old post
            editTimeout: 0,                // no timeout
            hasEditPerm: true,
            isAdmmod: false,
            isAdmin: false,
            posterIsAdmin: false,
            topicClosed: false,
            now: $now,
        ));
    }

    public function test_mod_can_edit_any_non_admin_post(): void
    {
        $now = time();
        $this->assertTrue($this->specification->isSatisfiedBy(
            posterId: 1,
            currentUserId: 2,              // moderator
            postTimestamp: $now - 86400,
            editTimeout: 600,
            hasEditPerm: false,
            isAdmmod: true,
            isAdmin: false,
            posterIsAdmin: false,
            topicClosed: true,
            now: $now,
        ));
    }
}
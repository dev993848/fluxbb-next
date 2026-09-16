<?php

declare(strict_types=1);

namespace FluxBB\Tests\Unit;

use FluxBB\Post\Application\Command\EditPostCommand;
use PHPUnit\Framework\TestCase;

/**
 * Tests for EditPostCommand value object.
 */
class EditPostCommandTest extends TestCase
{
    public function testCommandCanBeCreated(): void
    {
        $command = new EditPostCommand(
            postId: 1,
            newMessage: 'Updated content',
            editorId: 42,
            canEditOwn: true,
            isAdmmod: false,
            isAdmin: false,
            editTimeout: 600,
        );

        $this->assertSame(1, $command->postId);
        $this->assertSame('Updated content', $command->newMessage);
        $this->assertSame(42, $command->editorId);
    }
}
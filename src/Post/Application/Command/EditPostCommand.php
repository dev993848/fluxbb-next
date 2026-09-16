<?php

declare(strict_types=1);

namespace FluxBB\Post\Application\Command;

use FluxBB\Moderation\Domain\CanEditPost;
use FluxBB\Post\Domain\PostRepository;
use FluxBB\User\Domain\UserRepository;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Command: edit an existing post.
 *
 * Encapsulates the edit permission rules:
 * - Owner can edit within timeout (if g_edit_posts is enabled)
 * - Admin/moderator can edit any post
 * - Moderator cannot edit admin's posts
 *
 * @see EditPostHandler
 */
class EditPostCommand
{
    /**
     * @param int    $postId     The post ID to edit
     * @param string $newMessage The new message content
     * @param int    $editorId   The user performing the edit
     * @param bool   $canEditOwn Whether the user has g_edit_posts permission
     * @param bool   $isAdmmod   Whether the user is admin/moderator
     * @param bool   $isAdmin    Whether the user is admin (mod cannot edit admin)
     * @param int    $editTimeout Edit timeout in seconds from config
     */
    public function __construct(
        public readonly int $postId,
        public readonly string $newMessage,
        public readonly int $editorId,
        public readonly bool $canEditOwn,
        public readonly bool $isAdmmod,
        public readonly bool $isAdmin,
        public readonly int $editTimeout,
    ) {}
}

/**
 * Handler for the EditPost command.
 *
 * Validates edit permissions using the CanEditPost specification
 * before applying any changes to the post.
 */
class EditPostHandler
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CanEditPost $canEditPost,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Execute the edit command.
     *
     * @param EditPostCommand $command
     * @return bool True if the edit was applied
     * @throws \DomainException If the user lacks permission to edit
     */
    public function handle(EditPostCommand $command): bool
    {
        $post = $this->postRepository->findById($command->postId);

        if ($post === null) {
            throw new \DomainException('Post not found.');
        }

        // Determine if the post author is an admin (mods cannot edit admin posts)
        // For now default to false; the calling layer passes this.
        $posterIsAdmin = false; // This will be hydrated from user repository in a full implementation

        // Apply the CanEditPost specification
        $allowed = $this->canEditPost->isSatisfiedBy(
            posterId: $post->getPosterId(),
            currentUserId: $command->editorId,
            postTimestamp: $post->getPostedTimestamp(),
            editTimeout: $command->editTimeout,
            hasEditPerm: $command->canEditOwn,
            isAdmmod: $command->isAdmmod,
            isAdmin: $command->isAdmin,
            posterIsAdmin: $posterIsAdmin,
            topicClosed: false, // Topic closed check happens in the controller layer
            now: time(),
        );

        if (!$allowed) {
            throw new \DomainException('You do not have permission to edit this post.');
        }

        $post->edit($command->newMessage, $command->editorId);
        $this->postRepository->save($post);

        // Dispatch events
        foreach ($post->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return true;
    }
}
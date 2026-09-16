<?php

declare(strict_types=1);

namespace FluxBB\Post\Application\Command;

use FluxBB\Post\Domain\Post;
use FluxBB\Post\Domain\PostRepository;
use FluxBB\Topic\Domain\TopicRepository;
use FluxBB\User\Domain\UserRepository;
use FluxBB\User\Domain\Username;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Command: create a new post (or topic post).
 *
 * @see CreatePostHandler
 */
class CreatePostCommand
{
    /**
     * @param int         $forumId      The forum ID
     * @param int|null    $topicId      The topic ID (null if new topic)
     * @param string      $subject      Topic subject (required for new topics)
     * @param string      $message      Post message content (BBCode)
     * @param int         $posterId     The author's user ID
     * @param string      $poster       The author's username
     * @param string|null $posterIp     The author's IP address
     * @param bool        $hideSmilies  Whether to disable smilies
     */
    public function __construct(
        public readonly int $forumId,
        public readonly ?int $topicId,
        public readonly string $subject,
        public readonly string $message,
        public readonly int $posterId,
        public readonly string $poster,
        public readonly ?string $posterIp = null,
        public readonly bool $hideSmilies = false,
    ) {}
}

/**
 * Result of a create post operation.
 */
class CreatePostResult
{
    public function __construct(
        public readonly Post $post,
        public readonly int $topicId,
    ) {}
}

/**
 * Handler for the CreatePost command.
 */
class CreatePostHandler
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly TopicRepository $topicRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function handle(CreatePostCommand $command): CreatePostResult
    {
        // Create the post
        $post = new Post(
            id: 0,
            topicId: $command->topicId ?? 0,
            forumId: $command->forumId,
            posterId: $command->posterId,
            poster: $command->poster,
            message: $command->message,
            postedAt: new \DateTimeImmutable(),
            hideSmilies: $command->hideSmilies,
            posterIp: $command->posterIp,
        );

        $this->postRepository->save($post);

        // If new topic topicId was null, use the post's assigned topic ID
        $topicId = $command->topicId ?? $post->getId();

        // Dispatch domain events
        foreach ($post->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return new CreatePostResult($post, $topicId);
    }
}
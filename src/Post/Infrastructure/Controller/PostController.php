<?php

declare(strict_types=1);

namespace FluxBB\Post\Infrastructure\Controller;

use FluxBB\Forum\Domain\ForumRepository;
use FluxBB\Post\Application\Command\CreatePostCommand;
use FluxBB\Post\Application\Command\CreatePostHandler;
use FluxBB\Post\Application\Command\EditPostCommand;
use FluxBB\Post\Application\Command\EditPostHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Controller for post actions.
 *
 * Handles creating new posts, replying to topics, and editing posts.
 * Edit permission checks (domain scars) are delegated to EditPostHandler
 * via the CanEditPost specification.
 */
class PostController
{
    public function __construct(
        private readonly CreatePostHandler $createPostHandler,
        private readonly EditPostHandler $editPostHandler,
        private readonly TwigEnvironment $twig,
        private readonly ForumRepository $forumRepository,
    ) {}

    /**
     * Display the reply/new-post form.
     */
    public function createForm(Request $request): Response
    {
        $topicId = (int) $request->query->get('tid', 0);
        $forumId = (int) $request->query->get('fid', 0);

        $forums = [];
        if ($topicId === 0) {
            $grouped = $this->forumRepository->findAllGroupedByCategory();
            foreach ($grouped as $data) {
                foreach ($data['forums'] as $forum) {
                    $forums[] = ['id' => $forum->getId(), 'name' => $forum->getName()];
                }
            }
        }

        $content = $this->twig->render('post/create.html.twig', [
            'topic_id' => $topicId,
            'forum_id' => $forumId,
            'forums' => $forums,
        ]);

        return new Response($content);
    }

    /**
     * Handle post creation.
     */
    public function create(Request $request): Response
    {
        $message = (string) $request->request->get('req_message', '');
        $topicId = (int) $request->request->get('topic_id', 0);
        $forumId = (int) $request->request->get('forum_id', 0);
        $subject = (string) $request->request->get('req_subject', '');
        $posterId = (int) ($request->request->get('poster_id', 0));
        $poster = (string) $request->request->get('poster', '');

        try {
            $result = $this->createPostHandler->handle(new CreatePostCommand(
                forumId: $forumId,
                topicId: $topicId > 0 ? $topicId : null,
                subject: $subject,
                message: $message,
                posterId: $posterId,
                poster: $poster,
                posterIp: $request->getClientIp(),
            ));

            return new Response('', 302, ['Location' => '/topic/' . $result->topicId]);
        } catch (\DomainException $e) {
            $forums = [];
            if ($topicId === 0) {
                $grouped = $this->forumRepository->findAllGroupedByCategory();
                foreach ($grouped as $data) {
                    foreach ($data['forums'] as $forum) {
                        $forums[] = ['id' => $forum->getId(), 'name' => $forum->getName()];
                    }
                }
            }
            $content = $this->twig->render('post/create.html.twig', [
                'errors' => [$e->getMessage()],
                'message' => $message,
                'topic_id' => $topicId,
                'forum_id' => $forumId,
                'forums' => $forums,
                'subject' => $subject,
            ]);
            return new Response($content);
        }
    }

    /**
     * Display the edit-post form.
     *
     * @param Request $request The incoming HTTP request
     * @param array<string, scalar> $params Route parameters (expects 'id')
     * @return Response The edit form page
     */
    public function editForm(Request $request, array $params): Response
    {
        $postId = (int) ($params['id'] ?? 0);

        $content = $this->twig->render('post/edit.html.twig', [
            'post_id' => $postId,
        ]);

        return new Response($content);
    }

    /**
     * Handle post editing.
     *
     * @param Request $request The incoming HTTP request
     * @param array<string, scalar> $params Route parameters (expects 'id')
     * @return Response The result page or edit form with errors
     */
    public function edit(Request $request, array $params): Response
    {
        $postId = (int) ($params['id'] ?? 0);
        $newMessage = (string) $request->request->get('req_message', '');
        $editorId = (int) ($request->request->get('editor_id', 0));

        try {
            // Permission flags come from the session/auth layer
            // For now we use conservative defaults
            $this->editPostHandler->handle(new EditPostCommand(
                postId: $postId,
                newMessage: $newMessage,
                editorId: $editorId,
                canEditOwn: true,
                isAdmmod: false,
                isAdmin: false,
                editTimeout: 600,  // 10 minutes default
            ));

            return new Response(
                '<html><body><p>Post updated! <a href="/topic/' . $postId . '">View topic</a>.</p></body></html>'
            );
        } catch (\DomainException $e) {
            $content = $this->twig->render('post/edit.html.twig', [
                'errors' => [$e->getMessage()],
                'post_id' => $postId,
                'message' => $newMessage,
            ]);
            return new Response($content);
        }
    }
}
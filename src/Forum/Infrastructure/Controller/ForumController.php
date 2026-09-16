<?php

declare(strict_types=1);

namespace FluxBB\Forum\Infrastructure\Controller;

use FluxBB\Forum\Domain\ForumRepository;
use FluxBB\Shared\Domain\PostRepository;
use FluxBB\Topic\Domain\TopicRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment as TwigEnvironment;

/**
 * Controller for forum browsing pages.
 *
 * Handles the index page (list of categories and forums) and the
 * individual forum view (list of topics within a forum).
 */
class ForumController
{
    /**
     * @param ForumRepository $forumRepository Data access for forums
     * @param TopicRepository $topicRepository Data access for topics
     * @param TwigEnvironment $twig            Template rendering engine
     */
    public function __construct(
        private readonly ForumRepository $forumRepository,
        private readonly TopicRepository $topicRepository,
        private readonly TwigEnvironment $twig,
    ) {}

    /**
     * Display the forum index page.
     *
     * Shows all categories with their forums, total user count, and
     * the newest registered user.
     *
     * @param Request $request The incoming HTTP request
     * @return Response The rendered index page
     */
    public function index(Request $request): Response
    {
        $groupedForums = $this->forumRepository->findAllGroupedByCategory();

        $content = $this->twig->render('forum/index.html.twig', [
            'categories' => $groupedForums,
            'total_users' => 0,   // Will be implemented with User BC
            'last_user' => null,  // Will be implemented with User BC
        ]);

        return new Response($content);
    }

    /**
     * Display a single forum page with its topics.
     *
     * @param Request $request The incoming HTTP request
     * @param array<string, scalar> $params  Route parameters (expects 'id')
     * @return Response The rendered forum view
     *
     * @throws NotFoundHttpException If the forum does not exist
     */
    public function show(Request $request, array $params): Response
    {
        $forumId = (int) ($params['id'] ?? 0);
        $forum = $this->forumRepository->findById($forumId);

        if ($forum === null) {
            throw new NotFoundHttpException('Forum not found.');
        }

        $topics = $this->topicRepository->findByForum($forumId);

        $content = $this->twig->render('forum/view.html.twig', [
            'forum' => $forum,
            'topics' => $topics,
        ]);

        return new Response($content);
    }
}
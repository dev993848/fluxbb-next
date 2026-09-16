<?php

declare(strict_types=1);

namespace FluxBB\Topic\Infrastructure\Controller;

use FluxBB\Post\Domain\PostRepository;
use FluxBB\Topic\Domain\TopicRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment as TwigEnvironment;

/**
 * Controller for topic browsing pages.
 *
 * Handles the topic listing inside a forum and the single topic view
 * (list of posts within a topic).
 */
class TopicController
{
    public function __construct(
        private readonly TopicRepository $topicRepository,
        private readonly PostRepository $postRepository,
        private readonly TwigEnvironment $twig,
    ) {}

    /**
     * Display a single topic with its posts.
     *
     * @param Request $request The incoming HTTP request
     * @param array<string, scalar> $params Route parameters (expects 'id')
     * @return Response The rendered topic view
     *
     * @throws NotFoundHttpException If the topic does not exist
     */
    public function show(Request $request, array $params): Response
    {
        $topicId = (int) ($params['id'] ?? 0);
        $topic = $this->topicRepository->findById($topicId);

        if ($topic === null) {
            throw new NotFoundHttpException('Topic not found.');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $posts = $this->postRepository->findByTopic($topicId, $page);
        $totalPosts = $this->postRepository->countByTopic($topicId);
        $totalPages = max(1, (int) ceil($totalPosts / 20));

        $content = $this->twig->render('topic/view.html.twig', [
            'topic' => $topic,
            'posts' => $posts,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);

        return new Response($content);
    }
}
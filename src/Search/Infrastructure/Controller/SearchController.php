<?php

declare(strict_types=1);

namespace FluxBB\Search\Infrastructure\Controller;

use FluxBB\Search\Domain\SearchQuery;
use FluxBB\Search\Domain\SearchRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Production-grade search controller.
 *
 * Uses PostgreSQL full-text search (tsvector) with:
 * - Ranked results
 * - Excerpt with highlighted terms
 * - Forum/user filtering
 * - Pagination
 * - XSS-safe output
 */
class SearchController
{
    public function __construct(
        private readonly SearchRepository $searchRepository,
        private readonly TwigEnvironment $twig,
    ) {}

    /**
     * Display the search form.
     */
    public function showForm(Request $request): Response
    {
        $content = $this->twig->render('search/form.html.twig', [
            'keywords' => '',
        ]);

        return new Response($content);
    }

    /**
     * Execute a full-text search.
     */
    public function search(Request $request): Response
    {
        $keywords = (string) $request->query->get('keywords', '');
        $forumId = (int) $request->query->get('forum_id', 0);
        $userId = (int) $request->query->get('user_id', 0);
        $page = max(1, (int) $request->query->get('page', 1));

        if (trim($keywords) === '') {
            $content = $this->twig->render('search/form.html.twig', [
                'keywords' => $keywords,
                'error' => 'Please enter one or more keywords to search.',
            ]);
            return new Response($content);
        }

        $query = new SearchQuery(
            keywords: $keywords,
            forumId: $forumId,
            userId: $userId,
            page: $page,
            perPage: 20,
        );

        try {
            $result = $this->searchRepository->search($query);
        } catch (\Throwable $e) {
            $content = $this->twig->render('search/form.html.twig', [
                'keywords' => $keywords,
                'error' => 'Search failed: ' . $e->getMessage(),
            ]);
            return new Response($content);
        }

        $totalPages = max(1, (int) ceil($result['total'] / 20));

        $content = $this->twig->render('search/form.html.twig', [
            'keywords' => $keywords,
            'results' => $result['results'],
            'total' => $result['total'],
            'page' => $page,
            'total_pages' => $totalPages,
            'forum_id' => $forumId,
            'user_id' => $userId,
        ]);

        return new Response($content);
    }
}
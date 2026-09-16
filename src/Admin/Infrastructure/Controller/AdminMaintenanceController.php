<?php

declare(strict_types=1);

namespace FluxBB\Admin\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Admin maintenance controller — board maintenance actions.
 *
 * @see https://github.com/fluxbb/fluxbb/blob/master/admin_maintenance.php
 */
class AdminMaintenanceController
{
    public function __construct(
        private readonly Connection $db,
        private readonly CsrfMiddleware $csrf,
        private readonly TwigEnvironment $twig,
    ) {}

    public function index(Request $request): Response
    {
        $content = $this->twig->render('admin/maintenance.html.twig', [
            'message' => null,
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    /**
     * Rebuild forum statistics (topic/post counts).
     */
    public function rebuildStats(Request $request): Response
    {
        // Update forum post/topic counts
        $this->db->executeStatement('
            UPDATE forum_forums f SET
                num_topics = (SELECT COUNT(*) FROM forum_topics t WHERE t.forum_id = f.id),
                num_posts = (SELECT COUNT(*) FROM forum_posts p JOIN forum_topics t ON p.topic_id = t.id WHERE t.forum_id = f.id)
        ');

        $content = $this->twig->render('admin/maintenance.html.twig', [
            'message' => 'Forum statistics rebuilt successfully.',
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    /**
     * Prune old topics (older than N days, closed/sticky excluded).
     */
    public function prune(Request $request): Response
    {
        $days = (int) $request->request->get('days', 30);
        $cutoff = time() - ($days * 86400);

        $deleted = $this->db->executeStatement(
            'DELETE FROM forum_topics WHERE posted < ? AND closed = 0 AND sticky = 0 AND moved_to IS NULL',
            [$cutoff]
        );

        $content = $this->twig->render('admin/maintenance.html.twig', [
            'message' => "Pruned {$deleted} old topics.",
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    /**
     * Rebuild search index (re-calculate tsvector).
     */
    public function reindexSearch(Request $request): Response
    {
        $this->db->executeStatement(
            "UPDATE forum_posts SET search_vector = to_tsvector('english', COALESCE(message, ''))"
        );
        $this->db->executeStatement(
            "UPDATE forum_topics SET search_vector = to_tsvector('english', COALESCE(subject, ''))"
        );

        $content = $this->twig->render('admin/maintenance.html.twig', [
            'message' => 'Search index rebuilt successfully.',
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }
}
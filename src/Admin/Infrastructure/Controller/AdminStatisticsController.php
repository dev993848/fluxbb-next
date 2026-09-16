<?php

declare(strict_types=1);

namespace FluxBB\Admin\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Admin statistics controller — forum usage statistics.
 *
 * @see https://github.com/fluxbb/fluxbb/blob/master/admin_statistics.php
 */
class AdminStatisticsController
{
    public function __construct(
        private readonly Connection $db,
        private readonly CsrfMiddleware $csrf,
        private readonly TwigEnvironment $twig,
    ) {}

    public function index(Request $request): Response
    {
        $stats = [];

        // Basic counts
        $stats['num_users'] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM forum_users');
        $stats['num_topics'] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM forum_topics');
        $stats['num_posts'] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM forum_posts');
        $stats['num_forums'] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM forum_forums');
        $stats['num_categories'] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM forum_categories');
        $stats['num_bans'] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM forum_bans WHERE expire IS NULL OR expire > ' . time());
        $stats['num_reports_open'] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM forum_reports WHERE zapped IS NULL');
        $stats['num_subscriptions'] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM forum_topic_subscriptions');

        // Largest forum
        $largestForum = $this->db->fetchAssociative(
            'SELECT forum_name, num_topics, num_posts FROM forum_forums ORDER BY num_posts DESC LIMIT 1'
        );
        $stats['largest_forum'] = $largestForum ? (string) $largestForum['forum_name'] : '-';
        $stats['largest_forum_posts'] = $largestForum ? (int) $largestForum['num_posts'] : 0;

        // Latest registered user
        $latestUser = $this->db->fetchAssociative(
            'SELECT username, registered FROM forum_users ORDER BY id DESC LIMIT 1'
        );
        $stats['latest_user'] = $latestUser ? (string) $latestUser['username'] : '-';
        $stats['latest_user_date'] = $latestUser ? date('Y-m-d', (int) $latestUser['registered']) : '-';

        // Database size (PostgreSQL)
        $dbSize = $this->db->fetchAssociative(
            "SELECT pg_size_pretty(pg_database_size(current_database())) AS size"
        );
        $stats['db_size'] = $dbSize ? (string) $dbSize['size'] : '-';

        $content = $this->twig->render('admin/statistics.html.twig', [
            'stats' => $stats,
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }
}
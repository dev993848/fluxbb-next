<?php

declare(strict_types=1);

namespace FluxBB\Admin\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Admin reports controller — view and manage post reports.
 *
 * @see https://github.com/fluxbb/fluxbb/blob/master/admin_reports.php
 */
class AdminReportsController
{
    public function __construct(
        private readonly Connection $db,
        private readonly CsrfMiddleware $csrf,
        private readonly TwigEnvironment $twig,
    ) {}

    public function index(Request $request): Response
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT r.*, p.message AS post_message, u.username AS reporter_name
             FROM forum_reports r
             LEFT JOIN forum_posts p ON r.post_id = p.id
             LEFT JOIN forum_users u ON r.reported_by = u.id
             WHERE r.zapped IS NULL
             ORDER BY r.created DESC'
        );

        $content = $this->twig->render('admin/reports.html.twig', [
            'reports' => $rows,
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    public function zap(Request $request, array $params): Response
    {
        $reportId = (int) ($params['id'] ?? 0);
        $zappedBy = (int) ($request->request->get('moderator_id', 0));

        $this->db->update('forum_reports', [
            'zapped' => time(),
            'zapped_by' => $zappedBy,
        ], ['id' => $reportId]);

        return new Response('', 302, ['Location' => '/admin/reports']);
    }
}
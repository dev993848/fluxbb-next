<?php

declare(strict_types=1);

namespace FluxBB\Admin\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Admin group management controller.
 */
class AdminGroupController
{
    public function __construct(
        private readonly Connection $db,
        private readonly CsrfMiddleware $csrf,
        private readonly TwigEnvironment $twig,
    ) {}

    /**
     * List all user groups with their permissions.
     */
    public function index(Request $request): Response
    {
        $rows = $this->db->fetchAllAssociative('SELECT * FROM forum_groups ORDER BY g_id');
        $groups = [];
        foreach ($rows as $row) {
            $groups[] = $row;
        }

        $content = $this->twig->render('admin/groups.html.twig', [
            'groups' => $groups,
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    /**
     * Update group permissions.
     */
    public function update(Request $request): Response
    {
        $groupId = (int) $request->request->get('g_id', 0);
        $data = [
            'g_title' => (string) $request->request->get('g_title', ''),
            'g_user_title' => (string) $request->request->get('g_user_title', ''),
            'g_read_board' => (int) $request->request->get('g_read_board', 0),
            'g_post_replies' => (int) $request->request->get('g_post_replies', 0),
            'g_post_topics' => (int) $request->request->get('g_post_topics', 0),
            'g_post_flood' => (int) $request->request->get('g_post_flood', 30),
        ];

        $this->db->update('forum_groups', $data, ['g_id' => $groupId]);

        return new Response('', 302, ['Location' => '/admin/groups']);
    }
}
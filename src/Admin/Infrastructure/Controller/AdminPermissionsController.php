<?php

declare(strict_types=1);

namespace FluxBB\Admin\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Admin permissions controller — forum-level permissions per group.
 *
 * @see https://github.com/fluxbb/fluxbb/blob/master/admin_permissions.php
 */
class AdminPermissionsController
{
    public function __construct(
        private readonly Connection $db,
        private readonly CsrfMiddleware $csrf,
        private readonly TwigEnvironment $twig,
    ) {}

    public function index(Request $request): Response
    {
        $groups = $this->db->fetchAllAssociative('SELECT * FROM forum_groups ORDER BY g_id');
        $forums = $this->db->fetchAllAssociative('SELECT * FROM forum_forums ORDER BY disp_position');
        $perms = $this->db->fetchAllAssociative('SELECT * FROM forum_forum_perms ORDER BY group_id, forum_id');

        // Build matrix: group_id => forum_id => permissions
        $matrix = [];
        foreach ($perms as $perm) {
            $matrix[(int) $perm['group_id']][(int) $perm['forum_id']] = $perm;
        }

        $content = $this->twig->render('admin/permissions.html.twig', [
            'groups' => $groups,
            'forums' => $forums,
            'matrix' => $matrix,
            'selected_gid' => (int) $request->get('group_id', $groups[0]['g_id'] ?? 0),
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    public function save(Request $request): Response
    {
        $groupId = (int) $request->request->get('group_id', 0);
        $forumPerms = $request->request->all('perms', []);

        foreach ($forumPerms as $forumId => $permData) {
            $this->db->executeStatement(
                'INSERT INTO forum_forum_perms (group_id, forum_id, read_forum, post_replies, post_topics)
                 VALUES (?, ?, ?, ?, ?)
                 ON CONFLICT (group_id, forum_id) DO UPDATE SET
                    read_forum = EXCLUDED.read_forum,
                    post_replies = EXCLUDED.post_replies,
                    post_topics = EXCLUDED.post_topics',
                [
                    $groupId,
                    (int) $forumId,
                    (int) ($permData['read_forum'] ?? 0),
                    (int) ($permData['post_replies'] ?? 0),
                    (int) ($permData['post_topics'] ?? 0),
                ]
            );
        }

        return new Response('', 302, ['Location' => '/admin/permissions']);
    }
}
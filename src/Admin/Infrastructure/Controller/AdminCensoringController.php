<?php

declare(strict_types=1);

namespace FluxBB\Admin\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Admin censoring controller — manage censored words.
 *
 * @see https://github.com/fluxbb/fluxbb/blob/master/admin_censoring.php
 */
class AdminCensoringController
{
    public function __construct(
        private readonly Connection $db,
        private readonly CsrfMiddleware $csrf,
        private readonly TwigEnvironment $twig,
    ) {}

    public function index(Request $request): Response
    {
        $rows = $this->db->fetchAllAssociative('SELECT * FROM forum_censoring ORDER BY search_for');
        $content = $this->twig->render('admin/censoring.html.twig', [
            'words' => $rows,
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    public function add(Request $request): Response
    {
        $searchFor = (string) $request->request->get('search_for', '');
        $replaceWith = (string) $request->request->get('replace_with', '');

        if ($searchFor !== '') {
            $this->db->insert('forum_censoring', [
                'search_for' => $searchFor,
                'replace_with' => $replaceWith ?: '***',
            ]);
        }

        return new Response('', 302, ['Location' => '/admin/censoring']);
    }

    public function delete(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $this->db->delete('forum_censoring', ['id' => $id]);
        return new Response('', 302, ['Location' => '/admin/censoring']);
    }
}
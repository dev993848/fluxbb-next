<?php

declare(strict_types=1);

namespace FluxBB\Admin\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Admin options/settings controller.
 *
 * Provides CRUD for forum_config key-value store.
 */
class AdminOptionsController
{
    public function __construct(
        private readonly Connection $db,
        private readonly CsrfMiddleware $csrf,
        private readonly TwigEnvironment $twig,
    ) {}

    /**
     * List and edit all config options.
     */
    public function index(Request $request): Response
    {
        $rows = $this->db->fetchAllAssociative('SELECT * FROM forum_config ORDER BY conf_name');
        $config = [];
        foreach ($rows as $row) {
            $config[(string) $row['conf_name']] = (string) $row['conf_value'];
        }

        $content = $this->twig->render('admin/options.html.twig', [
            'config' => $config,
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    /**
     * Save updated config values.
     */
    public function save(Request $request): Response
    {
        $configValues = $request->request->all('config', []);

        foreach ($configValues as $name => $value) {
            $this->db->executeStatement(
                'INSERT INTO forum_config (conf_name, conf_value) VALUES (?, ?)
                 ON CONFLICT (conf_name) DO UPDATE SET conf_value = EXCLUDED.conf_value',
                [$name, (string) $value]
            );
        }

        return new Response('', 302, ['Location' => '/admin/options']);
    }
}
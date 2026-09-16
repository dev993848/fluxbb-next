<?php

declare(strict_types=1);

namespace FluxBB\Admin\Infrastructure\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Admin dashboard controller.
 *
 * Provides the entry point for the administration panel.
 * Individual admin sections (forums, users, bans, reports, etc.)
 * are handled by their respective BC controllers.
 */
class AdminController
{
    public function __construct(
        private readonly TwigEnvironment $twig,
    ) {}

    /**
     * Display the admin dashboard with links to all admin sections.
     *
     * @param Request $request The incoming HTTP request
     * @return Response The admin dashboard page
     */
    public function dashboard(Request $request): Response
    {
        $sections = [
            ['name' => 'Users', 'icon' => 'users', 'url' => '/admin/users', 'description' => 'Manage registered users'],
            ['name' => 'Groups', 'icon' => 'group', 'url' => '/admin/groups', 'description' => 'Manage user groups and permissions'],
            ['name' => 'Permissions', 'icon' => 'shield', 'url' => '/admin/permissions', 'description' => 'Forum-level group permissions'],
            ['name' => 'Bans', 'icon' => 'ban', 'url' => '/admin/bans', 'description' => 'Ban IPs, emails, usernames'],
            ['name' => 'Reports', 'icon' => 'report', 'url' => '/admin/reports', 'description' => 'View and manage reports'],
            ['name' => 'Censoring', 'icon' => 'eye-slash', 'url' => '/admin/censoring', 'description' => 'Censored word management'],
            ['name' => 'Options', 'icon' => 'settings', 'url' => '/admin/options', 'description' => 'Forum configuration'],
            ['name' => 'Maintenance', 'icon' => 'wrench', 'url' => '/admin/maintenance', 'description' => 'Rebuild, prune, reindex'],
            ['name' => 'Statistics', 'icon' => 'chart', 'url' => '/admin/statistics', 'description' => 'Forum usage statistics'],
        ];

        $content = $this->twig->render('admin/dashboard.html.twig', [
            'sections' => $sections,
        ]);

        return new Response($content);
    }
}
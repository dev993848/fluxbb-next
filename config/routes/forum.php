<?php

declare(strict_types=1);

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

$routes = new RouteCollection();

// --- Healthcheck ---
$routes->add('healthcheck', new Route('/healthcheck', [
    '_controller' => [\FluxBB\Shared\Infrastructure\Controller\HealthController::class, 'check'],
]));

// --- Forum Index ---
$routes->add('forum.index', new Route('/', [
    '_controller' => [\FluxBB\Forum\Infrastructure\Controller\ForumController::class, 'index'],
]));

// --- Forum View ---
$routes->add('forum.view', new Route('/forum/{id}', [
    '_controller' => [\FluxBB\Forum\Infrastructure\Controller\ForumController::class, 'show'],
], ['id' => '\d+']));

// --- Registration ---
$routes->add('user.register.form', new Route('/register', [
    '_controller' => [\FluxBB\User\Infrastructure\Controller\AuthController::class, 'registerForm'],
], [], [], '', [], ['GET']));
$routes->add('user.register', new Route('/register', [
    '_controller' => [\FluxBB\User\Infrastructure\Controller\AuthController::class, 'register'],
], [], [], '', [], ['POST']));

// --- Login / Logout ---
$routes->add('user.login.form', new Route('/login', [
    '_controller' => [\FluxBB\User\Infrastructure\Controller\AuthController::class, 'loginForm'],
], [], [], '', [], ['GET']));
$routes->add('user.login', new Route('/login', [
    '_controller' => [\FluxBB\User\Infrastructure\Controller\AuthController::class, 'login'],
], [], [], '', [], ['POST']));
$routes->add('user.logout', new Route('/logout', [
    '_controller' => [\FluxBB\User\Infrastructure\Controller\AuthController::class, 'logout'],
]));

// --- Password Reset ---
$routes->add('user.forgot_password.form', new Route('/forgot-password', [
    '_controller' => [\FluxBB\User\Infrastructure\Controller\AuthController::class, 'forgotPasswordForm'],
], [], [], '', [], ['GET']));
$routes->add('user.forgot_password', new Route('/forgot-password', [
    '_controller' => [\FluxBB\User\Infrastructure\Controller\AuthController::class, 'forgotPassword'],
], [], [], '', [], ['POST']));
$routes->add('user.reset_password.form', new Route('/reset-password/{token}', [
    '_controller' => [\FluxBB\User\Infrastructure\Controller\AuthController::class, 'resetPasswordForm'],
], ['token' => '.+']));
$routes->add('user.reset_password', new Route('/reset-password/{token}', [
    '_controller' => [\FluxBB\User\Infrastructure\Controller\AuthController::class, 'resetPassword'],
], ['token' => '.+'], [], '', [], ['POST']));

// --- Topic ---
$routes->add('topic.show', new Route('/topic/{id}', [
    '_controller' => [\FluxBB\Topic\Infrastructure\Controller\TopicController::class, 'show'],
], ['id' => '\d+']));

// --- Post ---
$routes->add('post.create.form', new Route('/post/new', [
    '_controller' => [\FluxBB\Post\Infrastructure\Controller\PostController::class, 'createForm'],
], [], [], '', [], ['GET']));
$routes->add('post.create', new Route('/post/new', [
    '_controller' => [\FluxBB\Post\Infrastructure\Controller\PostController::class, 'create'],
], [], [], '', [], ['POST']));
$routes->add('post.edit.form', new Route('/post/{id}/edit', [
    '_controller' => [\FluxBB\Post\Infrastructure\Controller\PostController::class, 'editForm'],
], ['id' => '\d+'], [], '', [], ['GET']));
$routes->add('post.edit', new Route('/post/{id}/edit', [
    '_controller' => [\FluxBB\Post\Infrastructure\Controller\PostController::class, 'edit'],
], ['id' => '\d+'], [], '', [], ['POST']));

// --- Subscription ---
$routes->add('subscription.subscribe', new Route('/topic/{topicId}/subscribe/{userId}', [
    '_controller' => [\FluxBB\Subscription\Infrastructure\Controller\SubscriptionController::class, 'subscribe'],
], ['topicId' => '\d+', 'userId' => '\d+']));
$routes->add('subscription.unsubscribe', new Route('/topic/{topicId}/unsubscribe/{userId}', [
    '_controller' => [\FluxBB\Subscription\Infrastructure\Controller\SubscriptionController::class, 'unsubscribe'],
], ['topicId' => '\d+', 'userId' => '\d+']));

// --- Admin Dashboard ---
$routes->add('admin.dashboard', new Route('/admin', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminController::class, 'dashboard'],
]));

// --- Admin: Users ---
$routes->add('admin.users', new Route('/admin/users', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminUserController::class, 'index'],
], [], [], '', [], ['GET']));
$routes->add('admin.users.delete', new Route('/admin/users/delete/{id}', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminUserController::class, 'delete'],
], ['id' => '\d+']));

// --- Admin: Options ---
$routes->add('admin.options', new Route('/admin/options', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminOptionsController::class, 'index'],
], [], [], '', [], ['GET']));
$routes->add('admin.options.save', new Route('/admin/options', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminOptionsController::class, 'save'],
], [], [], '', [], ['POST']));

// --- Admin: Bans ---
$routes->add('admin.bans', new Route('/admin/bans', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminBanController::class, 'index'],
], [], [], '', [], ['GET']));
$routes->add('admin.bans.create', new Route('/admin/bans', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminBanController::class, 'create'],
], [], [], '', [], ['POST']));
$routes->add('admin.bans.delete', new Route('/admin/bans/delete/{id}', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminBanController::class, 'delete'],
], ['id' => '\d+']));

// --- Admin: Groups ---
$routes->add('admin.groups', new Route('/admin/groups', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminGroupController::class, 'index'],
], [], [], '', [], ['GET']));
$routes->add('admin.groups.update', new Route('/admin/groups', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminGroupController::class, 'update'],
], [], [], '', [], ['POST']));

// --- Admin: Censoring ---
$routes->add('admin.censoring', new Route('/admin/censoring', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminCensoringController::class, 'index'],
], [], [], '', [], ['GET']));
$routes->add('admin.censoring.add', new Route('/admin/censoring', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminCensoringController::class, 'add'],
], [], [], '', [], ['POST']));
$routes->add('admin.censoring.delete', new Route('/admin/censoring/delete/{id}', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminCensoringController::class, 'delete'],
], ['id' => '\d+']));

// --- Admin: Permissions ---
$routes->add('admin.permissions', new Route('/admin/permissions', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminPermissionsController::class, 'index'],
], [], [], '', [], ['GET']));
$routes->add('admin.permissions.save', new Route('/admin/permissions', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminPermissionsController::class, 'save'],
], [], [], '', [], ['POST']));

// --- Admin: Reports ---
$routes->add('admin.reports', new Route('/admin/reports', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminReportsController::class, 'index'],
], [], [], '', [], ['GET']));
$routes->add('admin.reports.zap', new Route('/admin/reports/zap/{id}', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminReportsController::class, 'zap'],
], ['id' => '\d+'], [], '', [], ['POST']));

// --- Admin: Statistics ---
$routes->add('admin.statistics', new Route('/admin/statistics', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminStatisticsController::class, 'index'],
], [], [], '', [], ['GET']));

// --- Admin: Maintenance ---
$routes->add('admin.maintenance', new Route('/admin/maintenance', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminMaintenanceController::class, 'index'],
], [], [], '', [], ['GET']));
$routes->add('admin.maintenance.rebuild', new Route('/admin/maintenance/rebuild-stats', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminMaintenanceController::class, 'rebuildStats'],
], [], [], '', [], ['POST']));
$routes->add('admin.maintenance.prune', new Route('/admin/maintenance/prune', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminMaintenanceController::class, 'prune'],
], [], [], '', [], ['POST']));
$routes->add('admin.maintenance.reindex', new Route('/admin/maintenance/reindex', [
    '_controller' => [\FluxBB\Admin\Infrastructure\Controller\AdminMaintenanceController::class, 'reindexSearch'],
], [], [], '', [], ['POST']));

// --- Search ---
$routes->add('search.form', new Route('/search', [
    '_controller' => [\FluxBB\Search\Infrastructure\Controller\SearchController::class, 'showForm'],
], [], [], '', [], ['GET']));
$routes->add('search.execute', new Route('/search', [
    '_controller' => [\FluxBB\Search\Infrastructure\Controller\SearchController::class, 'search'],
], [], [], '', [], ['GET']));

return $routes;
<?php

declare(strict_types=1);

/**
 * FluxBB — Service Definitions (Production-Grade).
 *
 * PHP-DI definitions for all application services, repositories, controllers,
 * security middleware, and infrastructure components.
 *
 * @see config/config.php for base configuration values
 */

use FluxBB\Admin\Infrastructure\Controller\AdminBanController;
use FluxBB\Admin\Infrastructure\Controller\AdminCensoringController;
use FluxBB\Admin\Infrastructure\Controller\AdminController;
use FluxBB\Admin\Infrastructure\Controller\AdminGroupController;
use FluxBB\Admin\Infrastructure\Controller\AdminMaintenanceController;
use FluxBB\Admin\Infrastructure\Controller\AdminOptionsController;
use FluxBB\Admin\Infrastructure\Controller\AdminPermissionsController;
use FluxBB\Admin\Infrastructure\Controller\AdminReportsController;
use FluxBB\Admin\Infrastructure\Controller\AdminStatisticsController;
use FluxBB\Admin\Infrastructure\Controller\AdminUserController;
use FluxBB\Forum\Domain\ForumRepository;
use FluxBB\Forum\Infrastructure\Controller\ForumController;
use FluxBB\Forum\Infrastructure\Persistence\DoctrineForumRepository;
use FluxBB\Moderation\Domain\BanRepository;
use FluxBB\Moderation\Domain\CanEditPost;
use FluxBB\Moderation\Domain\FloodControl;
use FluxBB\Moderation\Infrastructure\Persistence\DoctrineBanRepository;
use FluxBB\Post\Domain\PostRepository;
use FluxBB\Post\Infrastructure\Controller\PostController;
use FluxBB\Post\Infrastructure\Parser\BBCodeParser;
use FluxBB\Post\Infrastructure\Persistence\DoctrinePostRepository;
use FluxBB\Search\Domain\SearchRepository;
use FluxBB\Search\Infrastructure\Controller\SearchController;
use FluxBB\Search\Infrastructure\Persistence\PostgresSearchRepository;
use FluxBB\Shared\Infrastructure\Bus\SimpleEventDispatcher;
use FluxBB\Shared\Infrastructure\Cache\CacheFactory;
use FluxBB\Shared\Infrastructure\Cache\CacheWarmer;
use FluxBB\Shared\Infrastructure\Controller\HealthController;
use FluxBB\Shared\Infrastructure\Database\DoctrineConnectionFactory;
use FluxBB\Shared\Infrastructure\Database\QueryCollector;
use FluxBB\Shared\Infrastructure\Database\QueryMonitor;
use FluxBB\Shared\Infrastructure\Mail\FluxBBMailer;
use FluxBB\Shared\Infrastructure\Persistence\DoctrineConfigRepository;
use FluxBB\Shared\Infrastructure\Security\CookieAuthProvider;
use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use FluxBB\Shared\Infrastructure\Security\PasswordHasher;
use FluxBB\Shared\Infrastructure\Security\RateLimiterMiddleware;
use FluxBB\Shared\Infrastructure\Time\SystemClock;
use FluxBB\Subscription\Application\NotifySubscribersOnNewPost;
use FluxBB\Subscription\Domain\SubscriptionRepository;
use FluxBB\Subscription\Infrastructure\Controller\SubscriptionController;
use FluxBB\Subscription\Infrastructure\Persistence\DoctrineSubscriptionRepository;
use FluxBB\Topic\Domain\TopicRepository;
use FluxBB\Topic\Infrastructure\Controller\TopicController;
use FluxBB\Topic\Infrastructure\Persistence\DoctrineTopicRepository;
use FluxBB\User\Domain\UserRepository;
use FluxBB\User\Infrastructure\Controller\AuthController;
use FluxBB\User\Infrastructure\Persistence\DoctrineUserRepository;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface as PsrContainer;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

use function DI\autowire;
use function DI\create;
use function DI\factory;
use function DI\get;

return [
    // --- Clock ---
    ClockInterface::class => create(SystemClock::class),

    // --- Event Dispatcher ---
    EventDispatcherInterface::class => create(SimpleEventDispatcher::class),

    // --- Cache ---
    CacheInterface::class => factory([CacheFactory::class, 'create']),
    CacheWarmer::class => autowire(),

    // --- Mailer ---
    FluxBBMailer::class => factory(function (PsrContainer $c) {
        return new FluxBBMailer(
            dsn: (string) ($_ENV['MAILER_DSN'] ?? $c->get('mailer.dsn')),
            fromAddress: (string) ($_ENV['MAILER_FROM'] ?? $c->get('mailer.from')),
            fromName: (string) ($_ENV['MAILER_FROM_NAME'] ?? $c->get('mailer.from_name')),
            logger: $c->get(Psr\Log\LoggerInterface::class),
        );
    }),

    // --- Password Hasher ---
    PasswordHasher::class => autowire(),

    // --- Security Middleware ---
    CookieAuthProvider::class => factory(function (PsrContainer $c) {
        return new CookieAuthProvider(
            cookieName: $c->get('cookie.name'),
            seed: $c->get('cookie.seed'),
        );
    }),
    CsrfMiddleware::class => autowire(),
    RateLimiterMiddleware::class => autowire(),

    // --- Database ---
    Doctrine\DBAL\Connection::class => factory(function (PsrContainer $c) {
        return DoctrineConnectionFactory::create($c);
    }),

    // --- Repositories ---
    ForumRepository::class => autowire(DoctrineForumRepository::class),
    UserRepository::class => autowire(DoctrineUserRepository::class),
    ConfigRepository::class => autowire(DoctrineConfigRepository::class),
    BanRepository::class => autowire(DoctrineBanRepository::class),
    SubscriptionRepository::class => autowire(DoctrineSubscriptionRepository::class),
    TopicRepository::class => autowire(DoctrineTopicRepository::class),
    PostRepository::class => autowire(DoctrinePostRepository::class),
    SearchRepository::class => autowire(PostgresSearchRepository::class),
    ConfigRepository::class => autowire(DoctrineConfigRepository::class),

    // --- Performance Monitor ---
    QueryCollector::class => autowire(),
    QueryMonitor::class => autowire(),

    // --- Domain Services ---
    CanEditPost::class => autowire(),
    FloodControl::class => autowire(),
    BBCodeParser::class => autowire(),

    // --- Twig ---
    TwigEnvironment::class => factory(function (PsrContainer $c) {
        $loader = new TwigFilesystemLoader(FLUXBB_ROOT . '/templates');
        $twig = new TwigEnvironment($loader, [
            'cache'            => $c->get('debug') ? false : FLUXBB_ROOT . '/var/cache/twig',
            'debug'            => $c->get('debug'),
            'strict_variables' => $c->get('debug'),
            'auto_reload'      => $c->get('debug'),
        ]);

        return $twig;
    }),

    // --- Command Handlers ---
    FluxBB\User\Application\Command\RegisterUserHandler::class => autowire(),
    FluxBB\User\Application\Command\LoginUserHandler::class => autowire(),

    // --- Controllers ---
    ForumController::class => autowire()
        ->constructorParameter('forumRepository', get(ForumRepository::class))
        ->constructorParameter('topicRepository', get(TopicRepository::class)),
    AuthController::class => autowire(),
    TopicController::class => autowire(),
    PostController::class => autowire(),
    SubscriptionController::class => autowire(),
    AdminController::class => autowire(),
    AdminUserController::class => autowire(),
    AdminOptionsController::class => autowire(),
    AdminBanController::class => autowire(),
    AdminGroupController::class => autowire(),
    AdminCensoringController::class => autowire(),
    AdminPermissionsController::class => autowire(),
    AdminReportsController::class => autowire(),
    AdminStatisticsController::class => autowire(),
    AdminMaintenanceController::class => autowire(),
    SearchController::class => autowire(),
    HealthController::class => autowire(),

    // --- Event Subscribers ---
    NotifySubscribersOnNewPost::class => autowire(),
    'event.subscriber.notify' => factory(function (PsrContainer $c) {
        $dispatcher = $c->get(EventDispatcherInterface::class);
        $dispatcher->addListener(
            \FluxBB\Post\Domain\PostCreated::class,
            $c->get(NotifySubscribersOnNewPost::class),
        );
    }),
];
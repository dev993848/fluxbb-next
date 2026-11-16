<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure;

use DI\ContainerBuilder;
use FluxBB\Shared\Infrastructure\Security\AdminAuthMiddleware;
use FluxBB\Shared\Infrastructure\Security\CookieAuthProvider;
use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use FluxBB\Shared\Infrastructure\Security\RateLimiterMiddleware;
use FluxBB\User\Domain\UserId;
use FluxBB\User\Domain\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment as TwigEnvironment;

/**
 * Application Kernel — Production-Grade.
 *
 * Extended with:
 * - Session support (Symfony HttpFoundation Session)
 * - Remember-me cookie restore (HMAC bound to password hash)
 * - CSRF protection on state-changing requests
 * - Admin authorization on /admin routes
 * - Rate limiting per IP
 * - Security headers (CSP, X-Frame-Options, HSTS)
 * - Proper error rendering in prod mode
 */
class Kernel
{
    private string $environment;
    private bool $debug;
    private \DI\Container $container;
    private ?Session $session = null;

    public function __construct(string $environment = 'prod', bool $debug = false)
    {
        $this->environment = $environment;
        $this->debug = $debug;
        $this->boot();
    }

    public function handle(Request $request): Response
    {
        try {
            // 1. Start session + restore remember-me if needed
            $session = $this->getSession();
            $request->setSession($session);
            if (!$session->isStarted()) {
                $session->start();
            }
            $this->restoreRememberMe($request);

            // 2. Set up routing context
            $context = new RequestContext();
            $context->fromRequest($request);

            // 3. Load routes and match
            $routes = $this->loadRoutes();
            $matcher = new UrlMatcher($routes, $context);
            $parameters = $matcher->match($request->getPathInfo());

            $routeName = (string) ($parameters['_route'] ?? '');

            // 4. Admin authorization
            if (str_starts_with($routeName, 'admin.') || str_starts_with($request->getPathInfo(), '/admin')) {
                /** @var AdminAuthMiddleware $adminAuth */
                $adminAuth = $this->container->get(AdminAuthMiddleware::class);
                $adminResponse = $adminAuth->handle($request);
                if ($adminResponse !== null) {
                    return $this->withSecurityHeaders($adminResponse, $request);
                }
            }

            // 5. CSRF check for POST/PUT/DELETE
            /** @var CsrfMiddleware $csrf */
            $csrf = $this->container->get(CsrfMiddleware::class);
            $csrfResponse = $csrf->handle($request);
            if ($csrfResponse !== null) {
                return $this->withSecurityHeaders($csrfResponse, $request);
            }

            // 6. Rate limiting
            /** @var RateLimiterMiddleware $rateLimiter */
            $rateLimiter = $this->container->get(RateLimiterMiddleware::class);
            $rateResponse = $rateLimiter->handle($request);
            if ($rateResponse !== null) {
                return $this->withSecurityHeaders($rateResponse, $request);
            }

            // Set Twig globals for all templates
            $this->setTwigGlobals($routeName, $request);

            // 7. Resolve and call controller
            $controller = $parameters['_controller'];
            unset($parameters['_controller'], $parameters['_route']);

            if (is_array($controller) && count($controller) === 2) {
                $controller[0] = $this->container->get($controller[0]);
            }

            $response = $controller($request, $parameters);
            if (!$response instanceof Response) {
                $response = new Response((string) $response);
            }

            return $this->withSecurityHeaders($response, $request);
        } catch (ResourceNotFoundException $e) {
            $response = new Response($this->renderError('404 Not Found'), 404);
        } catch (MethodNotAllowedException $e) {
            $response = new Response($this->renderError('405 Method Not Allowed'), 405);
        } catch (\Throwable $e) {
            if ($this->debug) {
                throw $e;
            }
            $response = new Response($this->renderError('500 Internal Server Error'), 500);
        }

        return $this->withSecurityHeaders($response, $request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->session !== null && $this->session->isStarted()) {
            $this->session->save();
        }
    }

    public function getContainer(): \DI\Container
    {
        return $this->container;
    }

    private function boot(): void
    {
        $containerBuilder = new ContainerBuilder();

        if (!defined('FLUXBB_ROOT')) {
            throw new \RuntimeException('FLUXBB_ROOT must be defined before booting the Kernel.');
        }

        $containerBuilder->addDefinitions(FLUXBB_ROOT . '/config/config.php');
        $containerBuilder->addDefinitions(FLUXBB_ROOT . '/config/services.php');

        $envConfig = FLUXBB_ROOT . '/config/config_' . $this->environment . '.php';
        if (file_exists($envConfig)) {
            $containerBuilder->addDefinitions($envConfig);
        }

        $this->container = $containerBuilder->build();
    }

    private function loadRoutes(): RouteCollection
    {
        $routes = new RouteCollection();

        $routeDir = FLUXBB_ROOT . '/config/routes';
        if (!is_dir($routeDir)) {
            return $routes;
        }

        $iterator = new \DirectoryIterator($routeDir);
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $loader = require $file->getPathname();
                if ($loader instanceof RouteCollection) {
                    $routes->addCollection($loader);
                }
            }
        }

        if ($routes->get('forum.index') === null) {
            $routes->add('home', new Route('/', [
                '_controller' => [\FluxBB\Forum\Infrastructure\Controller\ForumController::class, 'index'],
            ]));
        }

        return $routes;
    }

    /**
     * Restore session from remember-me cookie when session has no user_id.
     */
    private function restoreRememberMe(Request $request): void
    {
        $session = $request->getSession();
        if ($session->has('user_id')) {
            return;
        }

        if (!$this->container->has(CookieAuthProvider::class) || !$this->container->has(UserRepository::class)) {
            return;
        }

        /** @var CookieAuthProvider $cookieAuth */
        $cookieAuth = $this->container->get(CookieAuthProvider::class);
            $cookieRaw = $request->cookies->get($cookieAuth->getRememberCookieName());
            $raw = is_string($cookieRaw) ? $cookieRaw : '';
            if ($raw === '') {
                return;
            }

        $peekId = $cookieAuth->peekUserId($raw);
        if ($peekId === null) {
            return;
        }

        try {
            /** @var UserRepository $users */
            $users = $this->container->get(UserRepository::class);
            $user = $users->findById(new UserId($peekId));
            if ($user === null) {
                return;
            }

            if ($cookieAuth->validateCookie($raw, $user->getPasswordHash()) === null) {
                return;
            }

            $session->set('user_id', $user->getId()->toInt());
            $session->set('username', $user->getUsername()->toString());
            $session->set('group_id', $user->getGroupId()->value);
        } catch (\Throwable) {
            // Ignore restore failures (e.g. DB unavailable during early boot)
        }
    }

    /**
     * Set Twig globals shared by all templates.
     */
    private function setTwigGlobals(string $currentRoute, Request $request): void
    {
        if (!$this->container->has(TwigEnvironment::class)) {
            return;
        }

        try {
            /** @var TwigEnvironment $twig */
            $twig = $this->container->get(TwigEnvironment::class);
            $session = $request->getSession();
            $twig->addGlobal('current_route', $currentRoute);
            $twig->addGlobal('locale', 'en');
            $twig->addGlobal('is_logged_in', $session->has('user_id'));

            // Flash messages from session
            $flashMessages = [];
            if ($session->has('flash_message')) {
                $raw = $session->get('flash_message');
                if (is_string($raw)) {
                    $flashMessages[] = ['type' => 'info', 'text' => $raw];
                } elseif (is_array($raw) && isset($raw['text'])) {
                    $flashMessages[] = [
                        'type' => $raw['type'] ?? 'info',
                        'text' => $raw['text'],
                    ];
                } elseif (is_array($raw)) {
                    foreach ($raw as $item) {
                        if (is_array($item) && isset($item['text'])) {
                            $flashMessages[] = $item;
                        }
                    }
                }
                $session->remove('flash_message');
            }
            $twig->addGlobal('flash_messages', $flashMessages);
        } catch (\Throwable) {
            // Silently skip if Twig is not available
        }
    }

    private function getSession(): Session
    {
        if ($this->session === null) {
            $cookieName = $this->container->get('cookie.name');
            $dbConnection = null;
            try {
                $dbConnection = $this->container->get(\Doctrine\DBAL\Connection::class);
            } catch (\Throwable) {
                // No database session storage available — use native
            }

            $this->session = \FluxBB\Shared\Infrastructure\Security\SessionFactory::create(
                connection: $this->environment === 'prod' ? $dbConnection : null,
                cookieName: is_string($cookieName) ? $cookieName : 'fluxbb_cookie',
            );
        }

        return $this->session;
    }

    private function withSecurityHeaders(Response $response, Request $request): Response
    {
        $this->addSecurityHeaders($response, $request);

        return $response;
    }

    private function addSecurityHeaders(Response $response, Request $request): void
    {
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // CSP in production, relaxed in dev
        if ($this->environment === 'prod') {
            $response->headers->set('Content-Security-Policy',
                "default-src 'self'; " .
                "script-src 'self'; " .
                "style-src 'self' 'unsafe-inline'; " .
                "img-src 'self' data:; " .
                "font-src 'self'; " .
                "form-action 'self'; " .
                "frame-ancestors 'none';"
            );
        }

        // HSTS for HTTPS
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }

    private function renderError(string $message): string
    {
        return sprintf(
            '<!DOCTYPE html><html><head><title>%s</title>'
            . '<style>body{font-family:sans-serif;padding:40px;text-align:center}'
            . 'h1{font-size:48px;color:#c00}h2{color:#666}</style></head>'
            . '<body><h1>%s</h1><h2>FluxBB</h2></body></html>',
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        );
    }
}

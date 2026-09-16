<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure;

use DI\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

/**
 * Application Kernel — Production-Grade.
 *
 * Extended with:
 * - Session support (Symfony HttpFoundation Session)
 * - CSRF protection on state-changing requests
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
            // 1. Start session
            $session = $this->getSession();
            $request->setSession($session);
            if (!$session->isStarted()) {
                $session->start();
            }

            // 2. Set up routing context
            $context = new RequestContext();
            $context->fromRequest($request);

            // 3. Load routes and match
            $routes = $this->loadRoutes();
            $matcher = new UrlMatcher($routes, $context);
            $parameters = $matcher->match($request->getPathInfo());

            // 4. CSRF check for POST/PUT/DELETE
            $csrf = $this->container->get(\FluxBB\Shared\Infrastructure\Security\CsrfMiddleware::class);
            $csrfResponse = $csrf->handle($request);
            if ($csrfResponse !== null) {
                return $csrfResponse;
            }

            // 5. Rate limiting
            $rateLimiter = $this->container->get(\FluxBB\Shared\Infrastructure\Security\RateLimiterMiddleware::class);
            $rateResponse = $rateLimiter->handle($request);
            if ($rateResponse !== null) {
                return $rateResponse;
            }

            // Extract route name for Twig globals
            $routeName = $parameters['_route'] ?? '';

            // Set Twig globals for all templates
            $this->setTwigGlobals($routeName, $request);

            // 6. Resolve and call controller
            $controller = $parameters['_controller'];
            unset($parameters['_controller'], $parameters['_route']);

            if (is_array($controller) && count($controller) === 2) {
                $controller[0] = $this->container->get($controller[0]);
            }

            $response = $controller($request, $parameters);
            if (!$response instanceof Response) {
                $response = new Response((string) $response);
            }

            // 7. Add security headers
            $this->addSecurityHeaders($response, $request);

            return $response;
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

        $this->addSecurityHeaders($response, $request);
        return $response;
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
     * Set Twig globals shared by all templates.
     */
    private function setTwigGlobals(string $currentRoute, Request $request): void
    {
        if (!$this->container->has(\Twig\Environment::class)) {
            return;
        }

        try {
            $twig = $this->container->get(\Twig\Environment::class);
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
                cookieName: $cookieName,
            );
        }

        return $this->session;
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
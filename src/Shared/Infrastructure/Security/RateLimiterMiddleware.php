<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Security;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiter middleware.
 *
 * Implements flood control at the HTTP level (not the domain FloodControl spec).
 * Uses PSR-16 cache for storage (FileCache by default, Redis in production).
 * Default: 10 requests per 60 seconds per IP.
 */
class RateLimiterMiddleware
{
    private const int DEFAULT_MAX_REQUESTS = 60;
    private const int DEFAULT_WINDOW = 60;

    /**
     * @param CacheInterface $cache PSR-16 cache backend
     * @param int $maxRequests Max requests per window
     * @param int $window Window in seconds
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $maxRequests = self::DEFAULT_MAX_REQUESTS,
        private readonly int $window = self::DEFAULT_WINDOW,
    ) {}

    /**
     * Check if a request is rate-limited.
     *
     * @param Request $request The incoming request
     * @return Response|null 429 Response if blocked, null if allowed
     */
    public function handle(Request $request): ?Response
    {
        $ip = $request->getClientIp() ?? 'unknown';
        // Symfony Cache PSR-16 only allows alphanumeric, underscore, dot
        $safeIp = preg_replace('/[^a-zA-Z0-9_.]/', '_', $ip);
        $key = 'rate_limit_' . $safeIp;
        $windowKey = 'rate_limit_win_' . $safeIp;

        $current = $this->cache->get($key, 0);
        $windowStart = $this->cache->get($windowKey, time());

        // Reset if window expired
        if (time() - $windowStart > $this->window) {
            $this->cache->set($key, 1, $this->window);
            $this->cache->set($windowKey, time(), $this->window);
            return null;
        }

        if ($current >= $this->maxRequests) {
            return new Response('Too Many Requests', 429);
        }

        $this->cache->set($key, $current + 1, $this->window);

        return null;
    }
}
<?php

declare(strict_types=1);

namespace FluxBB\Tests\Unit;

use FluxBB\Shared\Infrastructure\Cache\FileCache;
use FluxBB\Shared\Infrastructure\Security\RateLimiterMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for RateLimiter middleware.
 */
class RateLimiterMiddlewareTest extends TestCase
{
    public function testAllowsFirstRequest(): void
    {
        $cache = new FileCache(sys_get_temp_dir() . '/fluxbb_test_' . uniqid('', true));
        $limiter = new RateLimiterMiddleware($cache, maxRequests: 3, window: 60);

        $request = Request::create('/test');

        $result = $limiter->handle($request);
        $this->assertNull($result);
    }

    public function testBlocksExcessiveRequests(): void
    {
        $cacheDir = sys_get_temp_dir() . '/fluxbb_test_' . uniqid('', true);
        $cache = new FileCache($cacheDir);
        $limiter = new RateLimiterMiddleware($cache, maxRequests: 2, window: 60);

        $request = Request::create('/test');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');

        // First 2 requests allowed
        $this->assertNull($limiter->handle($request));
        $this->assertNull($limiter->handle($request));

        // Third blocked
        $blocked = $limiter->handle($request);
        $this->assertNotNull($blocked);
        $this->assertSame(429, $blocked->getStatusCode());
    }
}
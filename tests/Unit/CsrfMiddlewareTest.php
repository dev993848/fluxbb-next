<?php

declare(strict_types=1);

namespace FluxBB\Tests\Unit;

use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests for CSRF middleware.
 */
class CsrfMiddlewareTest extends TestCase
{
    private CsrfMiddleware $csrf;

    protected function setUp(): void
    {
        $this->csrf = new CsrfMiddleware();
    }

    public function testGeneratesToken(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $token = $this->csrf->generateToken($request);
        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token)); // hex of 32 bytes
    }

    public function testTokenIsPersistedInSession(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $token1 = $this->csrf->generateToken($request);
        $token2 = $this->csrf->generateToken($request); // Same session

        $this->assertSame($token1, $token2);
    }

    public function testValidatesCorrectToken(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $token = $this->csrf->generateToken($request);
        $request->request->set('_csrf_token', $token);

        $result = $this->csrf->handle($request);
        $this->assertNull($result); // Allowed
    }
}
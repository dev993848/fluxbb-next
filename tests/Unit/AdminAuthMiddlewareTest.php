<?php

declare(strict_types=1);

namespace FluxBB\Tests\Unit;

use FluxBB\Shared\Infrastructure\Security\AdminAuthMiddleware;
use FluxBB\User\Domain\GroupId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class AdminAuthMiddlewareTest extends TestCase
{
    private AdminAuthMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new AdminAuthMiddleware();
    }

    public function testAnonymousRedirectsToLogin(): void
    {
        $request = Request::create('/admin');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $this->middleware->handle($request);
        $this->assertNotNull($response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->headers->get('Location'));
    }

    public function testMemberForbidden(): void
    {
        $request = Request::create('/admin');
        $session = new Session(new MockArraySessionStorage());
        $session->set('user_id', 10);
        $session->set('group_id', GroupId::Member->value);
        $request->setSession($session);

        $response = $this->middleware->handle($request);
        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testAdminAllowed(): void
    {
        $request = Request::create('/admin');
        $session = new Session(new MockArraySessionStorage());
        $session->set('user_id', 1);
        $session->set('group_id', GroupId::Admin->value);
        $request->setSession($session);

        $this->assertNull($this->middleware->handle($request));
    }

    public function testModeratorAllowed(): void
    {
        $request = Request::create('/admin');
        $session = new Session(new MockArraySessionStorage());
        $session->set('user_id', 2);
        $session->set('group_id', GroupId::Moderator->value);
        $request->setSession($session);

        $this->assertNull($this->middleware->handle($request));
    }
}

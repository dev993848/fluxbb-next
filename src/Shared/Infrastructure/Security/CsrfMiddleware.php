<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSRF protection middleware.
 *
 * Generates and validates CSRF tokens for state-changing requests (POST, PUT, DELETE).
 * Uses session-based tokens with double-submit.
 */
class CsrfMiddleware
{
    private const string SESSION_KEY = '_csrf_token';

    /**
     * Generate a new CSRF token for a form.
     *
     * @param Request $request The current request (for session access)
     * @return string The CSRF token
     */
    public function generateToken(Request $request): string
    {
        $session = $request->getSession();
        if (!$session->has(self::SESSION_KEY)) {
            $token = bin2hex(random_bytes(32));
            $session->set(self::SESSION_KEY, $token);
        }

        $token = $session->get(self::SESSION_KEY);

        return is_string($token) ? $token : '';
    }

    /**
     * Validate a CSRF token from the request.
     *
     * @param Request $request The current request
     * @return bool True if the token is valid
     */
    public function validateToken(Request $request): bool
    {
        $session = $request->getSession();
        if (!$session->has(self::SESSION_KEY)) {
            return false;
        }

        $expected = $session->get(self::SESSION_KEY);
        if (!is_string($expected) || $expected === '') {
            return false;
        }

        $fromRequest = $request->request->get('_csrf_token');
        $fromHeader = $request->headers->get('X-CSRF-Token');
        $actual = is_string($fromRequest) && $fromRequest !== ''
            ? $fromRequest
            : (is_string($fromHeader) ? $fromHeader : '');

        return hash_equals($expected, $actual);
    }

    /**
     * Check CSRF on POST/PUT/PATCH/DELETE requests.
     *
     * @param Request $request The incoming request
     * @return Response|null Null if allowed, 403 Response if blocked
     */
    public function handle(Request $request): ?Response
    {
        $method = strtoupper($request->getMethod());

        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return null;
        }

        if (!$this->validateToken($request)) {
            return new Response('CSRF token validation failed.', 403);
        }

        return null;
    }
}

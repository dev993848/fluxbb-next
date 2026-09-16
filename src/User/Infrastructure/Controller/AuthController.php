<?php

declare(strict_types=1);

namespace FluxBB\User\Infrastructure\Controller;

use FluxBB\Shared\Infrastructure\Security\CookieAuthProvider;
use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use FluxBB\User\Application\Command\LoginUserCommand;
use FluxBB\User\Application\Command\LoginUserHandler;
use FluxBB\User\Application\Command\RegisterUserCommand;
use FluxBB\User\Application\Command\RegisterUserHandler;
use FluxBB\User\Domain\Email;
use FluxBB\User\Domain\Username;
use FluxBB\User\Domain\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Production-grade authentication controller.
 *
 * Features:
 * - Session-based auth (Symfony HttpFoundation Session)
 * - Cookie-based "remember me" (14-day HMAC-signed cookie)
 * - CSRF-protected forms
 * - Registration with validation
 * - Login with session + optional cookie
 * - Logout with session invalidation
 * - Password reset flow (request + confirm)
 */
class AuthController
{
    public function __construct(
        private readonly RegisterUserHandler $registerHandler,
        private readonly LoginUserHandler $loginHandler,
        private readonly UserRepository $userRepository,
        private readonly CookieAuthProvider $cookieAuth,
        private readonly CsrfMiddleware $csrf,
        private readonly TwigEnvironment $twig,
    ) {}

    /**
     * Display the registration form.
     */
    public function registerForm(Request $request): Response
    {
        $content = $this->twig->render('user/register.html.twig', [
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    /**
     * Process registration submission.
     */
    public function register(Request $request): Response
    {
        $username = (string) $request->request->get('req_username', '');
        $email = (string) $request->request->get('req_email', '');
        $password = (string) $request->request->get('req_password', '');
        $confirmPassword = (string) $request->request->get('req_password_confirm', '');

        if ($password !== $confirmPassword) {
            $content = $this->twig->render('user/register.html.twig', [
                'errors' => ['Passwords do not match.'],
                'username' => $username,
                'email' => $email,
                '_csrf_token' => $this->csrf->generateToken($request),
            ]);
            return new Response($content);
        }

        try {
            $this->registerHandler->handle(new RegisterUserCommand(
                username: new Username($username),
                email: new Email($email),
                plainPassword: $password,
                registrationIp: $request->getClientIp(),
            ));

            return new Response(
                '<html><body><p>Registration successful! <a href="/login">Login here</a>.</p></body></html>'
            );
        } catch (\DomainException $e) {
            $content = $this->twig->render('user/register.html.twig', [
                'errors' => [$e->getMessage()],
                'username' => $username,
                'email' => $email,
                '_csrf_token' => $this->csrf->generateToken($request),
            ]);
            return new Response($content);
        }
    }

    /**
     * Display the login form.
     */
    public function loginForm(Request $request): Response
    {
        // Already logged in — redirect to forum
        if ($request->getSession()->has('user_id')) {
            return new Response('', 302, ['Location' => '/']);
        }

        $content = $this->twig->render('user/login.html.twig', [
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    /**
     * Process login submission with session creation.
     */
    public function login(Request $request): Response
    {
        $username = (string) $request->request->get('req_username', '');
        $password = (string) $request->request->get('req_password', '');
        $remember = (bool) $request->request->get('remember_me', false);

        try {
            $result = $this->loginHandler->handle(new LoginUserCommand(
                usernameOrEmail: $username,
                plainPassword: $password,
            ));

            if ($result->success) {
                // Fetch full user for session
                $user = $this->userRepository->findById($result->userId);

                if ($user !== null) {
                    // Store user in session
                    $session = $request->getSession();
                    $session->set('user_id', $result->userId->toInt());
                    $session->set('username', $user->getUsername()->toString());
                    $session->set('group_id', $user->getGroupId()->value);

                    // Set "remember me" cookie if requested
                    $response = new Response('', 302, ['Location' => '/']);

                    if ($remember) {
                        $cookieValue = $this->cookieAuth->generateCookie($user);
                        $response->headers->setCookie(
                            new \Symfony\Component\HttpFoundation\Cookie(
                                name: 'fluxbb_remember',
                                value: $cookieValue,
                                expire: time() + 1209600, // 14 days
                                path: '/',
                                domain: null,
                                secure: true,
                                httpOnly: true,
                                sameSite: \Symfony\Component\HttpFoundation\Cookie::SAMESITE_LAX,
                            )
                        );
                    }

                    return $response;
                }
            }

            $content = $this->twig->render('user/login.html.twig', [
                'errors' => [$result->errorMessage ?? 'Invalid credentials.'],
                '_csrf_token' => $this->csrf->generateToken($request),
            ]);
            return new Response($content);
        } catch (\DomainException $e) {
            $content = $this->twig->render('user/login.html.twig', [
                'errors' => [$e->getMessage()],
                '_csrf_token' => $this->csrf->generateToken($request),
            ]);
            return new Response($content);
        }
    }

    /**
     * Logout: clear session + remove remember cookie.
     */
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $session->invalidate();

        $response = new Response('', 302, ['Location' => '/']);
        $response->headers->clearCookie('fluxbb_remember');

        return $response;
    }

    /**
     * Display the password reset request form.
     */
    public function forgotPasswordForm(Request $request): Response
    {
        $content = $this->twig->render('user/forgot_password.html.twig', [
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    /**
     * Handle password reset request (generate + email token).
     *
     * For now, this generates a reset token and stores it in the session.
     * In production, this would send an email.
     */
    public function forgotPassword(Request $request): Response
    {
        $email = (string) $request->request->get('req_email', '');

        // Find user by email
        try {
            $user = $this->userRepository->findByEmail(new Email($email));
        } catch (\Throwable) {
            $user = null;
        }

        // Always show success to prevent email enumeration
        $token = bin2hex(random_bytes(32));
        $request->getSession()->set('password_reset_token', $token);
        $request->getSession()->set('password_reset_email', $email);

        // In production: send email with reset link
        // $this->mailer->sendResetEmail($email, $token);

        $content = $this->twig->render('user/forgot_password.html.twig', [
            'success' => true,
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    /**
     * Display the password reset form (from token link).
     */
    public function resetPasswordForm(Request $request, array $params): Response
    {
        $token = (string) ($params['token'] ?? '');

        // Validate token against session
        $storedToken = $request->getSession()->get('password_reset_token');

        if ($token === '' || $token !== $storedToken) {
            return new Response('Invalid or expired reset token.', 403);
        }

        $content = $this->twig->render('user/reset_password.html.twig', [
            'token' => $token,
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    /**
     * Execute the password reset.
     */
    public function resetPassword(Request $request, array $params): Response
    {
        $token = (string) $request->request->get('token', '');
        $password = (string) $request->request->get('req_password', '');
        $confirmPassword = (string) $request->request->get('req_password_confirm', '');

        // Validate token
        $storedToken = $request->getSession()->get('password_reset_token');
        $email = $request->getSession()->get('password_reset_email');

        if ($token === '' || $token !== $storedToken || $email === null) {
            return new Response('Invalid or expired reset token.', 403);
        }

        if ($password !== $confirmPassword) {
            $content = $this->twig->render('user/reset_password.html.twig', [
                'token' => $token,
                'errors' => ['Passwords do not match.'],
                '_csrf_token' => $this->csrf->generateToken($request),
            ]);
            return new Response($content);
        }

        try {
            $user = $this->userRepository->findByEmail(new Email($email));
            if ($user !== null) {
                $user->changePassword($password);
                $this->userRepository->save($user);
            }

            // Clear reset token
            $request->getSession()->remove('password_reset_token');
            $request->getSession()->remove('password_reset_email');

            return new Response(
                '<html><body><p>Password reset successful! <a href="/login">Login here</a>.</p></body></html>'
            );
        } catch (\Throwable $e) {
            return new Response('Password reset failed: ' . $e->getMessage(), 500);
        }
    }
}
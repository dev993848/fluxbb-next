<?php

declare(strict_types=1);

namespace FluxBB\Admin\Infrastructure\Controller;

use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use FluxBB\User\Domain\Email;
use FluxBB\User\Domain\Username;
use FluxBB\User\Domain\UserRepository;
use FluxBB\User\Infrastructure\Persistence\DoctrineUserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Admin user management controller.
 */
class AdminUserController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CsrfMiddleware $csrf,
        private readonly TwigEnvironment $twig,
    ) {}

    /**
     * List all users with pagination.
     */
    public function index(Request $request): Response
    {
        $users = $this->userRepository->findAll();

        $content = $this->twig->render('admin/users.html.twig', [
            'users' => $users,
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    /**
     * Delete a user.
     */
    public function delete(Request $request, array $params): Response
    {
        $userId = (int) ($params['id'] ?? 0);
        $user = $this->userRepository->findById(new \FluxBB\User\Domain\UserId($userId));

        if ($user !== null) {
            $this->userRepository->delete($user);
        }

        return new Response('', 302, ['Location' => '/admin/users']);
    }
}
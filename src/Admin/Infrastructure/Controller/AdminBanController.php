<?php

declare(strict_types=1);

namespace FluxBB\Admin\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use FluxBB\Moderation\Domain\Ban;
use FluxBB\Moderation\Domain\BanRepository;
use FluxBB\Shared\Infrastructure\Security\CsrfMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Admin ban management controller.
 */
class AdminBanController
{
    public function __construct(
        private readonly BanRepository $banRepository,
        private readonly CsrfMiddleware $csrf,
        private readonly TwigEnvironment $twig,
    ) {}

    /**
     * List all active bans.
     */
    public function index(Request $request): Response
    {
        $bans = $this->banRepository->findAllActive(new \DateTimeImmutable());

        $content = $this->twig->render('admin/bans.html.twig', [
            'bans' => $bans,
            '_csrf_token' => $this->csrf->generateToken($request),
        ]);
        return new Response($content);
    }

    /**
     * Create a new ban.
     */
    public function create(Request $request): Response
    {
        $ip = (string) $request->request->get('ip', '');
        $email = (string) $request->request->get('email', '');
        $username = (string) $request->request->get('username', '');
        $message = (string) $request->request->get('message', '');
        $expireDays = (int) $request->request->get('expire_days', 0);

        try {
            $expiresAt = $expireDays > 0
                ? new \DateTimeImmutable("+{$expireDays} days")
                : null;

            $ipMask = $ip !== '' ? new \FluxBB\Moderation\Domain\IpMask($ip) : null;

            $ban = new Ban(
                id: 0,
                ipMask: $ipMask,
                email: $email !== '' ? $email : null,
                username: $username !== '' ? $username : null,
                message: $message !== '' ? $message : null,
                expiresAt: $expiresAt,
                createdAt: new \DateTimeImmutable(),
            );

            $this->banRepository->save($ban);
        } catch (\InvalidArgumentException $e) {
            $bans = $this->banRepository->findAllActive(new \DateTimeImmutable());
            $content = $this->twig->render('admin/bans.html.twig', [
                'bans' => $bans,
                'error' => $e->getMessage(),
                '_csrf_token' => $this->csrf->generateToken($request),
            ]);
            return new Response($content);
        }

        return new Response('', 302, ['Location' => '/admin/bans']);
    }

    /**
     * Delete a ban.
     */
    public function delete(Request $request, array $params): Response
    {
        $banId = (int) ($params['id'] ?? 0);
        $ban = $this->banRepository->findById($banId);

        if ($ban !== null) {
            $this->banRepository->delete($ban);
        }

        return new Response('', 302, ['Location' => '/admin/bans']);
    }
}
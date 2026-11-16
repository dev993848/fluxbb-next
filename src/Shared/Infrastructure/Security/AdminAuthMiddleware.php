<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Security;

use FluxBB\User\Domain\GroupId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /admin routes: requires an authenticated admin or moderator.
 */
class AdminAuthMiddleware
{
    /**
     * @return Response|null Null when allowed; redirect/403 when blocked
     */
    public function handle(Request $request): ?Response
    {
        $session = $request->getSession();

        if (!$session->has('user_id')) {
            return new Response('', 302, ['Location' => '/login']);
        }

        $rawGroup = $session->get('group_id', GroupId::Guest->value);
        if (is_int($rawGroup)) {
            $groupId = $rawGroup;
        } elseif (is_numeric($rawGroup)) {
            $groupId = (int) $rawGroup;
        } else {
            $groupId = GroupId::Guest->value;
        }
        $allowed = [GroupId::Admin->value, GroupId::Moderator->value];

        if (!in_array($groupId, $allowed, true)) {
            return new Response('Forbidden', 403);
        }

        return null;
    }
}

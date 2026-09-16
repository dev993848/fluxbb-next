<?php

declare(strict_types=1);

namespace FluxBB\User\Application\Command;

use FluxBB\User\Domain\UserId;

/**
 * Command: authenticate a user with username/email and password.
 *
 * @see LoginUserHandler
 * @see LoginUserResult
 */
class LoginUserCommand
{
    /**
     * @param string $usernameOrEmail The username or email to authenticate
     * @param string $plainPassword   The plain-text password to verify
     * @param bool   $remember        Whether to persist the session (long-lived cookie)
     */
    public function __construct(
        public readonly string $usernameOrEmail,
        public readonly string $plainPassword,
        public readonly bool $remember = false,
    ) {}
}

/**
 * Result of a login attempt.
 *
 * Contains the user ID and whether the login succeeded.
 * On failure, includes an error message suitable for display.
 *
 * @see LoginUserHandler::handle()
 */
class LoginUserResult
{
    /**
     * @param UserId       $userId       The authenticated user's ID (0 on failure)
     * @param bool         $success      Whether authentication was successful
     * @param string|null  $errorMessage Human-readable error message on failure
     */
    public function __construct(
        public readonly UserId $userId,
        public readonly bool $success,
        public readonly ?string $errorMessage = null,
    ) {}
}
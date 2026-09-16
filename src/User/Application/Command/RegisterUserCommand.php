<?php

declare(strict_types=1);

namespace FluxBB\User\Application\Command;

use FluxBB\User\Domain\Email;
use FluxBB\User\Domain\Username;

/**
 * Command: register a new user.
 *
 * Contains the data needed to create a new user account:
 * - username (2-25 characters)
 * - email (validated format)
 * - plain-text password (will be hashed before storage)
 * - optional registration IP (for fraud detection and ban checks)
 *
 * @see RegisterUserHandler
 */
class RegisterUserCommand
{
    /**
     * @param Username $username       The chosen username
     * @param Email    $email          The user's email address
     * @param string   $plainPassword  Plain-text password (hashed in handler)
     * @param string|null $registrationIp The IP address used during registration
     */
    public function __construct(
        public readonly Username $username,
        public readonly Email $email,
        public readonly string $plainPassword,
        public readonly ?string $registrationIp = null,
    ) {}
}
<?php

declare(strict_types=1);

namespace FluxBB\User\Application\Command;

use FluxBB\User\Domain\UserId;

/**
 * Command: change a user's password.
 *
 * Requires the old password for verification and the new password to set.
 * Both are in plain text — hashing happens in the handler.
 *
 * @see ChangePasswordHandler
 */
class ChangePasswordCommand
{
    /**
     * @param UserId $userId          The user whose password to change
     * @param string $oldPlainPassword Current password for verification
     * @param string $newPlainPassword New password to set
     */
    public function __construct(
        public readonly UserId $userId,
        public readonly string $oldPlainPassword,
        public readonly string $newPlainPassword,
    ) {}
}
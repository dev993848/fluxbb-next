<?php

declare(strict_types=1);

namespace FluxBB\User\Application\Command;

use FluxBB\Shared\Infrastructure\Security\PasswordHasher;
use FluxBB\User\Domain\UserId;
use FluxBB\User\Domain\UserRepository;
use FluxBB\User\Domain\Username;

/**
 * Handler for the LoginUser command.
 *
 * Authenticates a user by username/email and plain password.
 * If the stored password hash uses an outdated algorithm or cost,
 * the hash is automatically upgraded (re-hashed on successful login).
 * Updates the last visit timestamp and dispatches UserLoggedIn event.
 */
class LoginUserHandler
{
    /**
     * @param UserRepository $userRepository For fetching the user by credentials
     * @param PasswordHasher $passwordHasher For verifying and optionally re-hashing
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PasswordHasher $passwordHasher,
    ) {}

    /**
     * Execute the login command.
     *
     * @param LoginUserCommand $command The login credentials
     * @return LoginUserResult The result of the authentication attempt
     */
    public function handle(LoginUserCommand $command): LoginUserResult
    {
        // Find user by username or email
        $user = $this->userRepository->findByUsername(new Username($command->usernameOrEmail));

        if ($user === null) {
            // Try by email (not yet implemented in this handler)
            // For now, return failure
            return new LoginUserResult(
                userId: new UserId(0),
                success: false,
                errorMessage: 'Invalid username/email or password.',
            );
        }

        // Verify password against stored hash
        if (!$this->passwordHasher->verify($command->plainPassword, $user->getPasswordHash())) {
            return new LoginUserResult(
                userId: new UserId(0),
                success: false,
                errorMessage: 'Invalid username/email or password.',
            );
        }

        // Auto-upgrade password hash if algorithm/cost has changed
        if ($this->passwordHasher->needsRehash($user->getPasswordHash())) {
            $user->changePassword($this->passwordHasher->hash($command->plainPassword));
            $this->userRepository->save($user);
        }

        // Record login (updates last visit, dispatches UserLoggedIn event)
        $user->recordLogin(new \DateTimeImmutable());
        $this->userRepository->save($user);

        return new LoginUserResult(
            userId: $user->getId(),
            success: true,
        );
    }
}
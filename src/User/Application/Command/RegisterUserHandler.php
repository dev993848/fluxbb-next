<?php

declare(strict_types=1);

namespace FluxBB\User\Application\Command;

use FluxBB\Shared\Infrastructure\Security\PasswordHasher;
use FluxBB\User\Domain\Email;
use FluxBB\User\Domain\GroupId;
use FluxBB\User\Domain\User;
use FluxBB\User\Domain\UserId;
use FluxBB\User\Domain\UserRepository;
use FluxBB\User\Domain\Username;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Handler for the RegisterUser command.
 *
 * Validates uniqueness of username and email, hashes the password,
 * creates the User aggregate, persists it, and dispatches the
 * UserRegistered domain event.
 */
class RegisterUserHandler
{
    /**
     * @param UserRepository              $userRepository  For checking duplicates and persisting
     * @param PasswordHasher              $passwordHasher  For hashing the plain password
     * @param EventDispatcherInterface    $eventDispatcher For dispatching UserRegistered
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PasswordHasher $passwordHasher,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Execute the registration command.
     *
     * @param RegisterUserCommand $command The registration data
     * @return User The newly created user
     *
     * @throws \DomainException If the username or email is already taken
     */
    public function handle(RegisterUserCommand $command): User
    {
        // Check if username already exists
        if ($this->userRepository->findByUsername($command->username) !== null) {
            throw new \DomainException('Username already taken.');
        }

        // Check if email already exists
        if ($this->userRepository->findByEmail($command->email) !== null) {
            throw new \DomainException('Email already registered.');
        }

        // Hash the password
        $passwordHash = $this->passwordHasher->hash($command->plainPassword);

        // Create user (ID 0 = temporary; repository assigns the real ID on save)
        $user = new User(
            id: new UserId(0),
            username: $command->username,
            email: $command->email,
            groupId: GroupId::Member,
            passwordHash: $passwordHash,
            registeredAt: new \DateTimeImmutable(),
            registrationIp: $command->registrationIp,
        );

        // Persist
        $this->userRepository->save($user);

        // Dispatch all recorded domain events
        foreach ($user->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $user;
    }
}
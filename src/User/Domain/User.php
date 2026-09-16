<?php

declare(strict_types=1);

namespace FluxBB\User\Domain;

use FluxBB\Shared\Domain\AggregateRoot;

/**
 * User aggregate root.
 *
 * Represents a registered user in the system. Each user belongs to a group
 * (admin, moderator, member, unverified, or guest) and has authentication
 * credentials, profile information, and moderation-related attributes.
 *
 * Domain events produced:
 * - UserRegistered: when a new user signs up
 * - UserLoggedIn: when a user successfully authenticates
 * - PasswordChanged: when the user changes their password
 */
class User extends AggregateRoot
{
    /**
     * @param UserId               $id           Unique user identifier
     * @param Username             $username     Username (2-25 chars)
     * @param Email                $email        Verified email address
     * @param GroupId              $groupId      Permission group membership
     * @param string               $passwordHash Hashed password (Argon2id/bcrypt)
     * @param \DateTimeImmutable   $registeredAt Registration timestamp
     * @param string|null          $registrationIp IP address at registration
     * @param string|null          $lastPostIp   IP address of last post
     * @param \DateTimeImmutable|null $lastVisit  Last successful login timestamp
     * @param bool                 $isAdmmod     Whether user is admin or moderator
     */
    public function __construct(
        private UserId $id,
        private Username $username,
        private Email $email,
        private GroupId $groupId,
        private string $passwordHash,
        private \DateTimeImmutable $registeredAt = new \DateTimeImmutable(),
        private ?string $registrationIp = null,
        private ?string $lastPostIp = null,
        private ?\DateTimeImmutable $lastVisit = null,
        private bool $isAdmmod = false,
    ) {}

    /**
     * Get the user's unique identifier.
     *
     * @return UserId The user ID
     */
    public function identity(): UserId
    {
        return $this->id;
    }

    /** @return UserId The user ID */
    public function getId(): UserId { return $this->id; }

    /** @return Username The username */
    public function getUsername(): Username { return $this->username; }

    /** @return Email The email address */
    public function getEmail(): Email { return $this->email; }

    /** @return GroupId The permission group */
    public function getGroupId(): GroupId { return $this->groupId; }

    /** @return string The password hash */
    public function getPasswordHash(): string { return $this->passwordHash; }

    /** @return \DateTimeImmutable The registration timestamp */
    public function getRegisteredAt(): \DateTimeImmutable { return $this->registeredAt; }

    /** @return string|null The IP address at registration */
    public function getRegistrationIp(): ?string { return $this->registrationIp; }

    /** @return string|null The IP of the last post */
    public function getLastPostIp(): ?string { return $this->lastPostIp; }

    /** @return \DateTimeImmutable|null The last visit timestamp */
    public function getLastVisit(): ?\DateTimeImmutable { return $this->lastVisit; }

    /** @return bool True if the user is admin or moderator */
    public function isAdmmod(): bool { return $this->isAdmmod; }

    /**
     * Record a successful login.
     *
     * Updates the last visit timestamp and records a UserLoggedIn event
     * for event listeners (e.g., online list update).
     *
     * @param \DateTimeImmutable $now The current timestamp
     */
    public function recordLogin(\DateTimeImmutable $now): void
    {
        $this->lastVisit = $now;
        $this->recordEvent(new UserLoggedIn($this->id, $now));
    }

    /**
     * Change the user's password.
     *
     * The new password must already be hashed before passing to this method.
     * Records a PasswordChanged event for auditing and cache invalidation.
     *
     * @param string $newPasswordHash The new Argon2id/bcrypt hash
     */
    public function changePassword(string $newPasswordHash): void
    {
        $this->passwordHash = $newPasswordHash;
        $this->recordEvent(new PasswordChanged($this->id));
    }

    /**
     * Update the user's email address.
     *
     * @param Email $newEmail The new validated email
     */
    public function updateEmail(Email $newEmail): void
    {
        $this->email = $newEmail;
    }
}
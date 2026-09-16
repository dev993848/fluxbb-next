<?php

declare(strict_types=1);

namespace FluxBB\Moderation\Domain;

use FluxBB\Shared\Domain\AggregateRoot;
use FluxBB\Shared\Domain\ValueObject;
use FluxBB\User\Domain\UserId;

/**
 * Ban aggregate root.
 *
 * Represents a ban placed on a user, IP address, email, or username.
 * Bans can target any combination of these four identifiers.
 *
 * Domain Scars recovered from original FluxBB:
 * - IP mask wildcard support ("192.168.*.*") — admin_bans.php
 * - Multi-axis ban (IP + email + username simultaneously)
 * - Ban expiry (timed bans auto-expire after a set date)
 * - Ban cache invalidation (cache_bans.php pattern)
 *
 * Domain events: BanCreated, BanExpired, BanDeleted
 */
class Ban extends AggregateRoot
{
    /**
     * @param int                 $id        Unique ban identifier
     * @param IpMask|null         $ipMask    Masked or exact IP address to ban
     * @param string|null         $email     Email address to ban (exact match)
     * @param string|null         $username  Username to ban (exact match)
     * @param string|null         $message   Ban message shown to the banned user
     * @param \DateTimeImmutable|null $expiresAt When the ban expires (null = permanent)
     * @param UserId|null         $createdBy The admin/moderator who created the ban
     * @param \DateTimeImmutable  $createdAt When the ban was created
     */
    public function __construct(
        private readonly int $id,
        private readonly ?IpMask $ipMask = null,
        private readonly ?string $email = null,
        private readonly ?string $username = null,
        private readonly ?string $message = null,
        private readonly ?\DateTimeImmutable $expiresAt = null,
        private readonly ?UserId $createdBy = null,
        private readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
        // At least one identifier must be set
        if ($ipMask === null && $email === null && $username === null) {
            throw new \DomainException('A ban must target at least one identifier (IP, email, or username).');
        }
    }

    /**
     * Get the ban's unique identifier.
     *
     * @return int The ban ID
     */
    public function identity(): int
    {
        return $this->id;
    }

    /** @return int The ban ID */
    public function getId(): int { return $this->id; }

    /** @return IpMask|null The banned IP mask */
    public function getIpMask(): ?IpMask { return $this->ipMask; }

    /** @return string|null The banned email */
    public function getEmail(): ?string { return $this->email; }

    /** @return string|null The banned username */
    public function getUsername(): ?string { return $this->username; }

    /** @return string|null The ban message */
    public function getMessage(): ?string { return $this->message; }

    /** @return \DateTimeImmutable|null The expiry timestamp */
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }

    /** @return UserId|null The creator user ID */
    public function getCreatedBy(): ?UserId { return $this->createdBy; }

    /** @return \DateTimeImmutable The creation timestamp */
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /**
     * Check if this ban has expired.
     *
     * A ban is considered expired if expiresAt is set and is in the past.
     * Permanent bans (no expiry) never expire.
     *
     * @param \DateTimeImmutable $now The current timestamp
     * @return bool True if the ban has expired
     */
    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < $now;
    }

    /**
     * Check if a given user context matches this ban.
     *
     * Checks all three axes (IP, email, username). A match on ANY
     * axis is sufficient to trigger the ban.
     *
     * @param string|null $ip       The user's current IP address
     * @param string|null $email    The user's email
     * @param string|null $username The user's username
     * @return bool True if the user context matches this ban
     */
    public function matches(?string $ip, ?string $email, ?string $username): bool
    {
        // Do not match expired bans
        if ($this->isExpiredAt(new \DateTimeImmutable())) {
            return false;
        }

        // Check IP mask
        if ($ip !== null && $this->ipMask !== null && $this->ipMask->matches($ip)) {
            return true;
        }

        // Check email (case-insensitive exact match)
        if ($email !== null && $this->email !== null
            && strcasecmp($this->email, $email) === 0) {
            return true;
        }

        // Check username (case-insensitive exact match)
        if ($username !== null && $this->username !== null
            && strcasecmp($this->username, $username) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Mark this ban as deleted (records BanDeleted event).
     */
    public function delete(): void
    {
        $this->recordEvent(new BanDeleted(
            banId: $this->id,
            reason: 'Manual deletion',
        ));
    }
}
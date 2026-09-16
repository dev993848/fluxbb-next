<?php

declare(strict_types=1);

namespace FluxBB\Moderation\Domain;

/**
 * Repository interface for Ban aggregate.
 *
 * Defines the persistence contract for ban lookup and management.
 * The all() method is used to build the cached ban list for
 * per-request ban checking (matching original cache_bans.php pattern).
 */
interface BanRepository
{
    /**
     * Load all non-expired bans.
     *
     * Used for building the ban cache that is checked on every request.
     *
     * @param \DateTimeImmutable $now The current time (to filter expired bans)
     * @return list<Ban> All active bans
     */
    public function findAllActive(\DateTimeImmutable $now): array;

    /**
     * Find a ban by its ID.
     *
     * @param int $id The ban ID
     * @return Ban|null The ban, or null if not found
     */
    public function findById(int $id): ?Ban;

    /**
     * Find all bans matching a specific IP address.
     *
     * Used when a user performs an action (posting, registering) and
     * their IP needs to be checked against all active ban IP masks.
     *
     * @param string $ip The IP address to check
     * @return list<Ban> Bans that match the given IP
     */
    public function findByIp(string $ip): array;

    /**
     * Persist a new ban.
     *
     * @param Ban $ban The ban to save
     */
    public function save(Ban $ban): void;

    /**
     * Delete a ban permanently.
     *
     * @param Ban $ban The ban to delete
     */
    public function delete(Ban $ban): void;
}
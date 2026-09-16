<?php

declare(strict_types=1);

namespace FluxBB\Shared\Domain;

/**
 * Repository for forum configuration key-value store.
 */
interface ConfigRepository
{
    /**
     * Find a config value by key.
     *
     * @param string $key Config key (e.g. 'o_board_title')
     * @return string|null Value or null if not found
     */
    public function find(string $key): ?string;

    /**
     * Find all config values as key-value pairs.
     *
     * @return array<string, string> All config entries
     */
    public function findAll(): array;

    /**
     * Save a config value (insert or update).
     *
     * @param string $key Config key
     * @param string $value Config value
     */
    public function upsert(string $key, string $value): void;

    /**
     * Delete a config entry by key.
     *
     * @param string $key Config key
     */
    public function delete(string $key): void;
}
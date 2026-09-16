<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Cache;

use FluxBB\Moderation\Domain\BanRepository;
use FluxBB\Shared\Domain\ConfigRepository;
use Psr\SimpleCache\CacheInterface;

/**
 * Cache warmer: pre-populates critical caches on deploy.
 *
 * Warms:
 * 1. Active bans list — used on every request for ban check
 * 2. Forum config — forum_config key-value store
 *
 * Run via CLI: php bin/console cache:warmup
 * Or triggered automatically on kernel boot via services.php
 */
class CacheWarmer
{
    private const string BANS_CACHE_KEY = 'fluxbb.bans.active';
    private const string CONFIG_CACHE_KEY = 'fluxbb.config.all';
    private const int TTL = 300; // 5 minutes

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly BanRepository $banRepository,
        private readonly ConfigRepository $configRepository,
    ) {}

    /**
     * Warm all application caches.
     *
     * @return array<string, bool> Cache key => success
     */
    public function warmAll(): array
    {
        return [
            'bans' => $this->warmBans(),
            'config' => $this->warmConfig(),
        ];
    }

    /**
     * Warm the active bans cache.
     */
    public function warmBans(): bool
    {
        try {
            $bans = $this->banRepository->findAllActive(new \DateTimeImmutable());
            return $this->cache->set(self::BANS_CACHE_KEY, $bans, self::TTL);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Warm the forum config cache.
     */
    public function warmConfig(): bool
    {
        try {
            $config = $this->configRepository->findAll();
            return $this->cache->set(self::CONFIG_CACHE_KEY, $config, self::TTL);
        } catch (\Throwable) {
            return false;
        }
    }
}
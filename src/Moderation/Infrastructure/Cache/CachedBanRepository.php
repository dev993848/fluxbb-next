<?php

declare(strict_types=1);

namespace FluxBB\Moderation\Infrastructure\Cache;

use FluxBB\Moderation\Domain\Ban;
use FluxBB\Moderation\Domain\BanCreated;
use FluxBB\Moderation\Domain\BanDeleted;
use FluxBB\Moderation\Domain\BanRepository;
use Psr\SimpleCache\CacheInterface;

/**
 * Cached ban repository — decorates BanRepository with PSR-16 cache.
 *
 * On BanCreated / BanDeleted events, the ban list cache is invalidated.
 * This replicates FluxBB's cache_bans.php pattern.
 *
 * @see https://github.com/fluxbb/fluxbb/blob/master/include/cache.php
 */
class CachedBanRepository implements BanRepository
{
    private const string CACHE_KEY = 'fluxbb.bans.active';

    public function __construct(
        private readonly BanRepository $inner,
        private readonly CacheInterface $cache,
    ) {}

    public function findAllActive(\DateTimeImmutable $now): array
    {
        $cached = $this->cache->get(self::CACHE_KEY);
        if ($cached !== null) {
            /** @var list<Ban> $bans */
            $bans = $cached;
            // Filter expired bans
            return array_values(
                array_filter($bans, fn (Ban $ban): bool => !$ban->isExpiredAt($now))
            );
        }

        $bans = $this->inner->findAllActive($now);
        $this->cache->set(self::CACHE_KEY, $bans, 300); // 5 minute TTL

        return $bans;
    }

    public function findById(int $id): ?Ban
    {
        return $this->inner->findById($id);
    }

    public function findByIp(string $ip): array
    {
        $active = $this->findAllActive(new \DateTimeImmutable());
        return array_values(
            array_filter(
                $active,
                fn (Ban $ban): bool => $ban->getIpMask() !== null && $ban->getIpMask()->matches($ip)
            )
        );
    }

    public function save(Ban $ban): void
    {
        $this->inner->save($ban);
        $this->cache->delete(self::CACHE_KEY);
    }

    public function delete(Ban $ban): void
    {
        $this->inner->delete($ban);
        $this->cache->delete(self::CACHE_KEY);
    }
}
<?php

declare(strict_types=1);

namespace FluxBB\Tests\Unit;

use FluxBB\Moderation\Infrastructure\Cache\CachedBanRepository;
use FluxBB\Moderation\Domain\Ban;
use FluxBB\Moderation\Domain\IpMask;
use FluxBB\Moderation\Domain\BanRepository;
use FluxBB\Shared\Infrastructure\Cache\FileCache;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CachedBanRepository.
 */
class CachedBanRepositoryTest extends TestCase
{
    private CachedBanRepository $repo;

    protected function setUp(): void
    {
        $inner = new class implements BanRepository {
            public array $bans = [];
            public int $callCount = 0;

            public function __construct()
            {
                $this->bans = [
                    new Ban(id: 1, ipMask: new IpMask('192.168.*.*'), createdAt: new \DateTimeImmutable()),
                    new Ban(id: 2, ipMask: new IpMask('10.0.0.1'), createdAt: new \DateTimeImmutable()),
                ];
            }

            public function findAllActive(\DateTimeImmutable $now): array
            {
                $this->callCount++;
                return $this->bans;
            }

            public function findById(int $id): ?Ban
            {
                return $this->bans[0] ?? null;
            }

            public function findByIp(string $ip): array
            {
                return array_values(
                    array_filter($this->bans, fn (Ban $b) => $b->getIpMask()?->matches($ip))
                );
            }

            public function save(Ban $ban): void {}
            public function delete(Ban $ban): void {}
        };

        $cache = new FileCache(sys_get_temp_dir() . '/fluxbb_cache_test_' . uniqid('', true));
        $this->repo = new CachedBanRepository($inner, $cache);
    }

    public function testCachesResults(): void
    {
        $bans = $this->repo->findAllActive(new \DateTimeImmutable());
        $this->assertCount(2, $bans);
    }

    public function testFindByIpUsesCache(): void
    {
        $matches = $this->repo->findByIp('192.168.1.1');
        $this->assertCount(1, $matches);
    }
}
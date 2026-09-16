<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Database;

/**
 * Simple PSR-3 logger that collects query logs for the QueryMonitor.
 *
 * @internal Used by DoctrineConnectionFactory to wire DBAL 4 logging middleware.
 */
class QueryCollector extends \Psr\Log\AbstractLogger
{
    /** @var list<array{sql: string, params: mixed[], duration: float}> */
    public array $queries = [];
    public int $count = 0;

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        if (isset($context['sql'])) {
            $this->count++;
            $this->queries[] = [
                'sql' => (string) $context['sql'],
                'params' => $context['params'] ?? [],
                'duration' => (float) ($context['duration'] ?? 0.0),
            ];
        }
    }

    /**
     * Reset the collector.
     */
    public function reset(): void
    {
        $this->queries = [];
        $this->count = 0;
    }
}

/**
 * Performance monitor — tracks query count and cache behavior.
 *
 * Collects query statistics from DBAL 4's Logging\Middleware.
 * Provides query count, duration, slowest query, cache hit/miss stats.
 *
 * Usage:
 *   $monitor = $container->get(QueryMonitor::class);
 *   $report = $monitor->getBenchmarkReport();
 *
 * @see https://www.doctrine-project.org/projects/doctrine-dbal/en/4.0/reference/logging.html
 */
class QueryMonitor
{
    private int $cacheHits = 0;
    private int $cacheMisses = 0;

    public function __construct(
        private readonly QueryCollector $collector,
    ) {}

    /**
     * Track a cache hit.
     */
    public function recordCacheHit(): void
    {
        $this->cacheHits++;
    }

    /**
     * Track a cache miss.
     */
    public function recordCacheMiss(): void
    {
        $this->cacheMisses++;
    }

    /**
     * Get the total number of database queries executed.
     *
     * @return int Query count
     */
    public function getQueryCount(): int
    {
        return $this->collector->count;
    }

    /**
     * Get the full query log with durations.
     *
     * @return list<array{sql: string, params: mixed[], duration: float}>
     */
    public function getQueryLog(): array
    {
        return $this->collector->queries;
    }

    /**
     * Get cache statistics.
     *
     * @return array{hits: int, misses: int, total: int, hit_rate: float}
     */
    public function getCacheStats(): array
    {
        $total = $this->cacheHits + $this->cacheMisses;
        return [
            'hits' => $this->cacheHits,
            'misses' => $this->cacheMisses,
            'total' => $total,
            'hit_rate' => $total > 0 ? round(($this->cacheHits / $total) * 100, 2) : 0.0,
        ];
    }

    /**
     * Get a benchmark report as a formatted array.
     *
     * @return array{queries: int, cache_hits: int, cache_misses: int, hit_rate: float, slowest_query: string|null, max_duration: float}
     */
    public function getBenchmarkReport(): array
    {
        $maxDuration = 0.0;
        $slowestQuery = null;

        foreach ($this->collector->queries as $entry) {
            if ($entry['duration'] > $maxDuration) {
                $maxDuration = $entry['duration'];
                $slowestQuery = $entry['sql'];
            }
        }

        $stats = $this->getCacheStats();

        return [
            'queries' => $this->collector->count,
            'cache_hits' => $stats['hits'],
            'cache_misses' => $stats['misses'],
            'hit_rate' => $stats['hit_rate'],
            'slowest_query' => $slowestQuery,
            'max_duration' => round($maxDuration, 4),
        ];
    }

    /**
     * Reset all counters.
     */
    public function reset(): void
    {
        $this->collector->reset();
        $this->cacheHits = 0;
        $this->cacheMisses = 0;
    }
}
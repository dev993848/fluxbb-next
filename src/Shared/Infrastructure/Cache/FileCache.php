<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * File-based PSR-16 cache implementation.
 */
class FileCache implements CacheInterface
{
    private string $cacheDir;

    /** @var array<string, array{mixed, int}> */
    private array $runtimeCache = [];

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/\\');

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->runtimeCache)) {
            [$value, $expiresAt] = $this->runtimeCache[$key];
            if ($expiresAt === 0 || $expiresAt >= time()) {
                return $value;
            }
            unset($this->runtimeCache[$key]);
        }

        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return $default;
        }

        $rawContents = file_get_contents($file);
        if ($rawContents === false) {
            return $default;
        }

        $data = @unserialize($rawContents);
        if ($data === false) {
            @unlink($file);
            return $default;
        }

        [$value, $expiresAt] = $data;

        if ($expiresAt !== 0 && $expiresAt < time()) {
            @unlink($file);
            return $default;
        }

        $this->runtimeCache[$key] = [$value, $expiresAt];
        return $value;
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        $expiresAt = match (true) {
            $ttl === null                => 0,
            $ttl instanceof \DateInterval => (new \DateTimeImmutable())->add($ttl)->getTimestamp(),
            default                       => time() + $ttl,
        };

        $this->runtimeCache[$key] = [$value, $expiresAt];
        $file = $this->getFilePath($key);
        $dir = dirname($file);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return file_put_contents($file, serialize([$value, $expiresAt])) !== false;
    }

    public function delete(string $key): bool
    {
        unset($this->runtimeCache[$key]);
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    public function clear(): bool
    {
        $this->runtimeCache = [];
        return $this->clearDirectory($this->cacheDir);
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    private function getFilePath(string $key): string
    {
        $safeKey = str_replace(
            ['{', '}', '(', ')', '/', '\\', '@', ':'],
            '_',
            $key
        );
        return $this->cacheDir . '/' . substr($safeKey, 0, 2) . '/' . $safeKey . '.cache';
    }

    private function clearDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                @unlink((string) $fileInfo->getRealPath());
            } elseif ($fileInfo->isDir()) {
                @rmdir((string) $fileInfo->getRealPath());
            }
        }

        return true;
    }
}
<?php

declare(strict_types=1);

namespace FluxBB\Tests\Performance;

use PHPUnit\Framework\TestCase;

/**
 * Performance benchmarks for FluxBB core operations.
 *
 * Measures query count and cache behavior for key operations.
 * These are not pass/fail tests — they collect metrics.
 *
 * Run: php vendor/bin/phpunit --group=performance
 */
class FluxBBBenchmarkTest extends TestCase
{
    /**
     * Benchmark: BBCode parser throughput.
     *
     * Parses a complex BBCode message 1000 times to measure throughput.
     *
     * @group performance
     */
    public function testBBCodeParsingThroughput(): void
    {
        $parser = new \FluxBB\Post\Infrastructure\Parser\BBCodeParser(smiliesEnabled: true);

        $complexMessage = "[quote=Admin][b]Important announcement:[/b]\n"
            . "[list][*]Point 1[*]Point 2[list][*]Sub-item 1[*]Sub-item 2[/list][*]Point 3[/list]\n"
            . "[url=https://example.com]Link[/url] with [color=red]colored[/color] text. :)\n"
            . "[code]<?php echo 'hello'; ?>[/code][/quote]";

        $iterations = 1000;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $parser->parse($complexMessage);
        }

        $duration = microtime(true) - $start;
        $throughput = $iterations / $duration;

        $this->addToAssertionCount(1);

        // Log benchmark info (visible with --verbose)
        fwrite(STDERR, sprintf(
            "\n[Benchmark] BBCode parsing: %d iterations in %.4fs = %.2f ops/sec\n",
            $iterations,
            $duration,
            $throughput
        ));
    }

    /**
     * Benchmark: BBCode strip throughput.
     *
     * @group performance
     */
    public function testBBCodeStripThroughput(): void
    {
        $parser = new \FluxBB\Post\Infrastructure\Parser\BBCodeParser(smiliesEnabled: false);

        $complexMessage = "[b]bold[/b] [i]italic[/i] [url=https://example.com]link[/url] and plain text.";

        $iterations = 2000;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $parser->stripBBCode($complexMessage);
        }

        $duration = microtime(true) - $start;
        $throughput = $iterations / $duration;

        $this->addToAssertionCount(1);

        fwrite(STDERR, sprintf(
            "[Benchmark] BBCode strip: %d iterations in %.4fs = %.2f ops/sec\n",
            $iterations,
            $duration,
            $throughput
        ));
    }

    /**
     * Benchmark: Password hashing cost.
     *
     * Argon2id with default cost parameters.
     *
     * @group performance
     * @group slow
     */
    public function testPasswordHashingBenchmark(): void
    {
        $hasher = new \FluxBB\Shared\Infrastructure\Security\PasswordHasher();

        $passwords = [
            'short',
            'average-length-password-123',
            'a-very-long-password-that-exceeds-the-typical-maximum-length',
        ];

        foreach ($passwords as $pw) {
            $start = microtime(true);
            $hash = $hasher->hash($pw);
            $hashDuration = microtime(true) - $start;

            $start = microtime(true);
            $hasher->verify($pw, $hash);
            $verifyDuration = microtime(true) - $start;

            fwrite(STDERR, sprintf(
                "[Benchmark] Password (len=%d): hash=%.4fs, verify=%.4fs\n",
                strlen($pw),
                $hashDuration,
                $verifyDuration
            ));
        }

        $this->addToAssertionCount(1);
    }

    /**
     * Benchmark: CSRF token generation.
     *
     * @group performance
     */
    public function testCsrfTokenGeneration(): void
    {
        $csrf = new \FluxBB\Shared\Infrastructure\Security\CsrfMiddleware();

        $session = new \Symfony\Component\HttpFoundation\Session\Session(
            new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()
        );
        $request = \Symfony\Component\HttpFoundation\Request::create('/test');
        $request->setSession($session);

        $iterations = 10000;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $csrf->generateToken($request);
        }

        $duration = microtime(true) - $start;
        $throughput = $iterations / $duration;

        $this->addToAssertionCount(1);

        fwrite(STDERR, sprintf(
            "[Benchmark] CSRF token generation: %d iterations in %.4fs = %.2f ops/sec\n",
            $iterations,
            $duration,
            $throughput
        ));
    }

    /**
     * Benchmark: IP mask matching (ban system).
     *
     * @group performance
     */
    public function testIpMaskMatchingThroughput(): void
    {
        $masks = [
            new \FluxBB\Moderation\Domain\IpMask('192.168.*.*'),
            new \FluxBB\Moderation\Domain\IpMask('10.0.0.*'),
            new \FluxBB\Moderation\Domain\IpMask('172.16.*.*'),
            new \FluxBB\Moderation\Domain\IpMask('*.*.*.*'),
            new \FluxBB\Moderation\Domain\IpMask('192.168.1.*'),
        ];

        $ips = [
            '192.168.1.100',
            '10.0.0.1',
            '172.16.0.1',
            '203.0.113.42',
        ];

        $iterations = 10000;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            foreach ($masks as $mask) {
                foreach ($ips as $ip) {
                    $mask->matches($ip);
                }
            }
        }

        $duration = microtime(true) - $start;
        $throughput = ($iterations * count($masks) * count($ips)) / $duration;

        $this->addToAssertionCount(1);

        fwrite(STDERR, sprintf(
            "[Benchmark] IP mask matching: %d checks in %.4fs = %.2f checks/sec\n",
            $iterations * count($masks) * count($ips),
            $duration,
            $throughput
        ));
    }

    /**
     * Benchmark: Rate limiter throughput.
     *
     * @group performance
     */
    public function testRateLimiterThroughput(): void
    {
        $cache = new \FluxBB\Shared\Infrastructure\Cache\FileCache(
            sys_get_temp_dir() . '/fluxbb_bench_rate_' . uniqid('', true)
        );
        $limiter = new \FluxBB\Shared\Infrastructure\Security\RateLimiterMiddleware($cache, 10000, 60);

        $request = \Symfony\Component\HttpFoundation\Request::create('/test');

        $iterations = 1000;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $limiter->handle($request);
        }

        $duration = microtime(true) - $start;
        $throughput = $iterations / $duration;

        $this->addToAssertionCount(1);

        fwrite(STDERR, sprintf(
            "[Benchmark] Rate limiter: %d checks in %.4fs = %.2f checks/sec\n",
            $iterations,
            $duration,
            $throughput
        ));
    }
}
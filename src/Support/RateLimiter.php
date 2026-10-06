<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Goletter\HyperfExceptionNotify\Support;

use Closure;
use Hyperf\Redis\Redis;
use Hyperf\Support\Traits\InteractsWithTime;
use Throwable;

use function Goletter\Utils\stdoutLogger;

class RateLimiter
{
    use InteractsWithTime;

    protected const HIT_SCRIPT = <<<'LUA'
local hits = redis.call('INCR', KEYS[1])
if hits == 1 then
    redis.call('EXPIRE', KEYS[1], ARGV[1])
end
return hits
LUA;

    /**
     * The configured limit object resolvers.
     */
    protected array $limiters = [];

    /**
     * Per-worker fallback counters used while Redis is unavailable.
     *
     * @var array<string, array{hits: int, expires_at: int}>
     */
    protected array $localHits = [];

    /**
     * Create a new rate limiter instance.
     */
    public function __construct(protected Redis $redis) {}

    /**
     * Atomically count a hit and report whether it is still within the limit.
     *
     * Increment-then-compare keeps concurrent coroutines from all slipping past a
     * separate "check" step. Falls back to a per-worker counter if Redis fails.
     */
    public function hitWithinLimit(string $key, int $maxAttempts, int $decaySeconds = 60): bool
    {
        $key = $this->cleanRateLimiterKey($key);
        $decaySeconds = max(1, $decaySeconds);

        try {
            $hits = (int) $this->redis->eval(self::HIT_SCRIPT, [$key, $decaySeconds], 1);
        } catch (Throwable $exception) {
            stdoutLogger()->warning('Exception notify rate limiter fell back to local counter: ' . $exception->getMessage());
            $hits = $this->localHit($key, $decaySeconds);
        }

        return $hits <= $maxAttempts;
    }

    /**
     * Register a named limiter configuration.
     *
     * @return $this
     */
    public function for(string $name, Closure $callback): static
    {
        $this->limiters[$name] = $callback;

        return $this;
    }

    /**
     * Get the given named rate limiter.
     */
    public function limiter(string $name): ?Closure
    {
        return $this->limiters[$name] ?? null;
    }

    /**
     * Attempts to execute a callback if it's not limited.
     */
    public function attempt(string $key, int $maxAttempts, Closure $callback, int $decaySeconds = 60): mixed
    {
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        return \Hyperf\Tappable\tap($callback() ?: true, function () use ($key, $decaySeconds) {
            $this->hit($key, $decaySeconds);
        });
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        if ($this->attempts($key) >= $maxAttempts) {
            if ($this->redis->get($this->cleanRateLimiterKey($key) . ':timer')) {
                return true;
            }

            $this->resetAttempts($key);
        }

        return false;
    }

    /**
     * Get the number of attempts for the given key.
     */
    public function attempts(string $key): int
    {
        $key = $this->cleanRateLimiterKey($key);

        return (int) $this->redis->get($key);
    }

    /**
     * Clean the rate limiter key from unicode characters.
     */
    public function cleanRateLimiterKey(string $key): string
    {
        return preg_replace('/&([a-z])[a-z]+;/i', '$1', htmlentities($key));
    }

    /**
     * Reset the number of attempts for the given key.
     */
    public function resetAttempts(string $key): int
    {
        $key = $this->cleanRateLimiterKey($key);

        return $this->redis->del($key);
    }

    /**
     * Increment the counter for a given key for a given decay time.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $key = $this->cleanRateLimiterKey($key);

        $this->redis->set(
            $key . ':timer',
            $this->availableAt($decaySeconds),
            $decaySeconds
        );

        $hits = $this->redis->incr($key);
        $hits === 1 and $this->redis->expire($key, $decaySeconds);

        return $hits;
    }

    /**
     * Get the number of retries left for the given key.
     */
    public function retriesLeft(string $key, int $maxAttempts): int
    {
        return $this->remaining($key, $maxAttempts);
    }

    /**
     * Get the number of retries left for the given key.
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        $key = $this->cleanRateLimiterKey($key);

        $attempts = $this->attempts($key);

        return $maxAttempts - $attempts;
    }

    /**
     * Clear the hits and lockout timer for the given key.
     */
    public function clear(string $key)
    {
        $key = $this->cleanRateLimiterKey($key);

        $this->resetAttempts($key);

        $this->redis->del($key . ':timer');
    }

    /**
     * Get the number of seconds until the "key" is accessible again.
     */
    public function availableIn(string $key): int
    {
        $key = $this->cleanRateLimiterKey($key);

        return max(0, $this->redis->get($key . ':timer') - $this->currentTime());
    }

    protected function localHit(string $key, int $decaySeconds): int
    {
        $now = $this->currentTime();
        foreach ($this->localHits as $name => $entry) {
            if ($entry['expires_at'] <= $now) {
                unset($this->localHits[$name]);
            }
        }

        $this->localHits[$key] ??= ['hits' => 0, 'expires_at' => $now + $decaySeconds];

        return ++$this->localHits[$key]['hits'];
    }
}

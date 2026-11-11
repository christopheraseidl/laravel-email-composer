<?php

namespace CSeidl\EmailComposer\Services;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Support\Collection;

/**
 * Reads and counts the limits of the `email-composer` rate limiter, which the
 * service provider registers from config and an app may register over.
 */
class SendRateLimiter
{
    public const NAME = 'email-composer';

    public function __construct(protected RateLimiter $limiter) {}

    /**
     * Reserves one send against every limit, or reserves nothing and returns false when
     * one is full. Each counter is incremented first and judged by the value the cache
     * returns, so two workers cannot both take the last slot, as they could between a
     * separate read and increment. A send that then throws keeps its slot.
     */
    public function attempt(): bool
    {
        $reserved = [];

        foreach ($this->limits() as $limit) {
            $count = $this->limiter->increment($this->key($limit), $limit->decaySeconds);
            $reserved[] = $limit;

            if ($count > $limit->maxAttempts) {
                foreach ($reserved as $taken) {
                    $this->limiter->decrement($this->key($taken), $taken->decaySeconds);
                }

                return false;
            }
        }

        return true;
    }

    /** Sends every limit still allows in its current window. null means no limit. */
    public function remaining(): ?int
    {
        $remaining = array_map(
            fn (Limit $limit) => $this->limiter->remaining($this->key($limit), $limit->maxAttempts),
            $this->limits(),
        );

        return $remaining === [] ? null : max(0, min($remaining));
    }

    /** @return array<int, Limit> */
    protected function limits(): array
    {
        $callback = $this->limiter->limiter(self::NAME);
        $limits = $callback === null ? Limit::none() : $callback();

        if ($limits instanceof Unlimited) {
            return [];
        }

        return Collection::wrap($limits)->filter(fn ($limit) => $limit instanceof Limit)->values()->all();
    }

    /** The key Laravel's RateLimited middleware would use for the same limit. */
    protected function key(Limit $limit): string
    {
        return md5(self::NAME.$limit->key);
    }
}

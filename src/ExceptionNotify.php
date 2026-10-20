<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify;

use Goletter\HyperfExceptionNotify\Channels\DingTalkChannel;
use Goletter\HyperfExceptionNotify\Channels\FeiShuChannel;
use Goletter\HyperfExceptionNotify\Channels\LogChannel;
use Goletter\HyperfExceptionNotify\Channels\NotifyAbstractChannel;
use Goletter\HyperfExceptionNotify\Channels\WeWorkChannel;
use Goletter\HyperfExceptionNotify\Jobs\ReportExceptionJob;
use Goletter\HyperfExceptionNotify\Support\Manager;
use Goletter\HyperfExceptionNotify\Support\RateLimiter;
use Guanguans\Notify\Factory;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Stringable\Str;
use Throwable;

use function Goletter\Utils\arrayFilterFilled;
use function Goletter\Utils\stdoutLogger;
use function Hyperf\Collection\value;
use function Hyperf\Support\env;

class ExceptionNotify extends Manager
{
    /**
     * Channels selected via onChannel(). Only ever set on a clone, because this
     * class is a container singleton shared by all coroutines.
     *
     * @var list<string>
     */
    protected array $selectedChannels = [];

    public function __construct(
        protected CollectorManager $collectorManager,
        protected ConfigInterface $config,
        protected RateLimiter $rateLimiter
    ) {
    }

    public function reportIf(mixed $condition, Throwable $throwable, null|array|string $channels = null): void
    {
        value($condition) and $this->report($throwable, $channels);
    }

    /**
     * @param null|list<string>|string $channels overrides onChannel() / report_channels for this call
     */
    public function report(Throwable $throwable, null|array|string $channels = null): void
    {
        try {
            if (! $this->passesFilters($throwable)) {
                return;
            }

            $channels = array_values(array_filter(
                $channels === null ? $this->resolveChannels() : $this->normalizeChannels($channels),
                fn (string $channel): bool => $this->channelReady($channel)
            ));
            if ($channels === [] || $this->isRateLimited($throwable)) {
                return;
            }

            $this->dispatchReportExceptionJob($throwable, $channels);
        } catch (Throwable $exception) {
            stdoutLogger()->error('Exception notify failed: ' . $exception->getMessage(), ['exception' => $exception]);
        }
    }

    /**
     * Note: consumes a rate limit attempt when the exception is otherwise reportable.
     */
    public function shouldntReport(Throwable $throwable): bool
    {
        return ! $this->passesFilters($throwable) || $this->isRateLimited($throwable);
    }

    public function shouldReport(Throwable $throwable): bool
    {
        return ! $this->shouldntReport($throwable);
    }

    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('exception_notify.default', 'log');
    }

    /**
     * Returns a copy bound to the given channels; the shared instance is left untouched.
     */
    public function onChannel(null|array|string $channels = null): static
    {
        $clone = clone $this;
        $clone->selectedChannels = $channels === null ? [] : $this->normalizeChannels($channels);

        return $clone;
    }

    /**
     * Resolve channel names that should receive this report.
     *
     * @return list<string>
     */
    public function resolveChannels(): array
    {
        if ($this->selectedChannels !== []) {
            return $this->selectedChannels;
        }

        $configured = $this->config->get('exception_notify.report_channels');
        if (is_array($configured) && $configured !== []) {
            return $this->normalizeChannels($configured);
        }

        return [$this->getDefaultDriver()];
    }

    protected function passesFilters(Throwable $throwable): bool
    {
        if (! $this->config->get('exception_notify.enabled', true)) {
            return false;
        }

        $env = (array) $this->config->get('exception_notify.env', ['*']);
        $appEnv = (string) $this->config->get('app_env', env('APP_ENV', 'local'));
        if (! Str::is($env, $appEnv)) {
            return false;
        }

        foreach ((array) $this->config->get('exception_notify.dont_report', []) as $type) {
            if (is_string($type) && $throwable instanceof $type) {
                return false;
            }
        }

        return true;
    }

    protected function isRateLimited(Throwable $throwable): bool
    {
        // Fingerprint by location + message (not full trace), so the same error is grouped.
        $fingerprint = md5(implode('|', [
            $throwable::class,
            $throwable->getFile(),
            (string) $throwable->getLine(),
            $throwable->getMessage(),
        ]));

        return ! $this->rateLimiter->hitWithinLimit(
            'exception_notify:' . $fingerprint,
            (int) $this->config->get('exception_notify.rate_limiter.max_attempts', 1),
            (int) $this->config->get('exception_notify.rate_limiter.decay_seconds', 300)
        );
    }

    /**
     * @param list<string> $channels
     */
    protected function dispatchReportExceptionJob(Throwable $throwable, array $channels): void
    {
        $report = $this->collectorManager->toReport($throwable);
        $async = (bool) $this->config->get('exception_notify.async', true);
        $queue = (string) $this->config->get('exception_notify.queue', 'default');

        foreach ($channels as $channel) {
            $job = new ReportExceptionJob($channel, $report);
            if ($async && $channel !== 'log' && $this->pushToQueue($queue, $job)) {
                continue;
            }

            $job->handle();
        }
    }

    /**
     * Returns false when the queue is unavailable so the caller can send synchronously.
     */
    protected function pushToQueue(string $queue, ReportExceptionJob $job): bool
    {
        try {
            return ApplicationContext::getContainer()
                ->get(DriverFactory::class)
                ->get($queue)
                ->push($job);
        } catch (Throwable $exception) {
            stdoutLogger()->warning('Exception notify queue push failed, sending synchronously: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Skip IM channels that have no token configured.
     */
    protected function channelReady(string $channel): bool
    {
        if ($channel === 'log') {
            return true;
        }

        $token = $this->config->get(sprintf('exception_notify.channels.%s.token', $channel));

        return is_string($token) && $token !== '';
    }

    /**
     * @return list<string>
     */
    protected function normalizeChannels(array|string $channels): array
    {
        if (is_string($channels)) {
            $channels = explode(',', $channels);
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($channel): string => trim((string) $channel),
            $channels
        ))));
    }

    protected function createLogDriver(): LogChannel
    {
        return new LogChannel(
            (string) $this->config->get('exception_notify.channels.log.channel', 'default'),
            (string) $this->config->get('exception_notify.channels.log.level', 'error'),
            'log'
        );
    }

    protected function createFeiShuDriver(): FeiShuChannel
    {
        return $this->createNotifyDriver(FeiShuChannel::class, 'feiShu', 'feiShu');
    }

    protected function createDingTalkDriver(): DingTalkChannel
    {
        return $this->createNotifyDriver(DingTalkChannel::class, 'dingTalk', 'DingTalk');
    }

    protected function createWeWorkDriver(): WeWorkChannel
    {
        return $this->createNotifyDriver(WeWorkChannel::class, 'weWork', 'weWork');
    }

    /**
     * @template T of NotifyAbstractChannel
     * @param class-string<T> $channelClass
     * @return T
     */
    protected function createNotifyDriver(string $channelClass, string $name, string $factoryMethod): NotifyAbstractChannel
    {
        $client = Factory::{$factoryMethod}(arrayFilterFilled([
            'token' => $this->config->get(sprintf('exception_notify.channels.%s.token', $name)),
            'secret' => $this->config->get(sprintf('exception_notify.channels.%s.secret', $name)),
        ]));

        return new $channelClass($client, $name);
    }
}

<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify;

use Goletter\HyperfExceptionNotify\Channels\DingTalkChannel;
use Goletter\HyperfExceptionNotify\Channels\FeiShuChannel;
use Goletter\HyperfExceptionNotify\Channels\LogChannel;
use Goletter\HyperfExceptionNotify\Channels\WeWorkChannel;
use Goletter\HyperfExceptionNotify\Jobs\ReportExceptionJob;
use Goletter\HyperfExceptionNotify\Support\Manager;
use Goletter\HyperfExceptionNotify\Support\RateLimiter;
use Guanguans\Notify\Factory;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Stringable\Str;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

use function Goletter\Utils\arrayFilterFilled;
use function Goletter\Utils\stdoutLogger;
use function Hyperf\Collection\value;
use function Hyperf\Config\config;
use function Hyperf\Support\env;

class ExceptionNotify extends Manager
{
    /**
     * Channels selected via onChannel() for the next report.
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

    /**
     * @param mixed $condition
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function reportIf($condition, Throwable $throwable): void
    {
        value($condition) and $this->report($throwable);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function report(Throwable $throwable): void
    {
        try {
            if ($this->shouldntReport($throwable)) {
                return;
            }
            $this->dispatchReportExceptionJob($throwable);
        } catch (Throwable $throwable) {
            stdoutLogger()->error($throwable->getMessage(), ['exception' => $throwable]);
        } finally {
            $this->selectedChannels = [];
        }
    }

    public function shouldntReport(Throwable $throwable): bool
    {
        if (! $this->config->get('exception_notify.enabled', true)) {
            return true;
        }

        $env = (array) $this->config->get('exception_notify.env', ['*']);
        $appEnv = (string) env('APP_ENV', 'local');
        if (! Str::is($env, $appEnv)) {
            return true;
        }

        foreach ((array) $this->config->get('exception_notify.dont_report', []) as $type) {
            if (is_string($type) && $throwable instanceof $type) {
                return true;
            }
        }

        // Fingerprint by location + message (not full trace), so rate limit actually works.
        $fingerprint = md5(implode('|', [
            $throwable::class,
            $throwable->getFile(),
            (string) $throwable->getLine(),
            $throwable->getMessage(),
        ]));

        $allowed = $this->rateLimiter->attempt(
            'exception_notify:' . $fingerprint,
            (int) $this->config->get('exception_notify.rate_limiter.max_attempts', 1),
            static fn (): bool => true,
            (int) $this->config->get('exception_notify.rate_limiter.decay_seconds', 300)
        );

        return ! $allowed;
    }

    public function shouldReport(Throwable $throwable): bool
    {
        return ! $this->shouldntReport($throwable);
    }

    public function getDefaultDriver(): string
    {
        return (string) config('exception_notify.default', 'log');
    }

    public function onChannel(null|array|string $channels = null): self
    {
        if ($channels === null) {
            return $this;
        }

        if (is_string($channels)) {
            $channels = array_filter(array_map('trim', explode(',', $channels)));
        }

        $this->selectedChannels = array_values(array_unique(array_map('strval', $channels)));

        return $this;
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
            return array_values(array_map('strval', $configured));
        }

        return [$this->getDefaultDriver()];
    }

    protected function dispatchReportExceptionJob(Throwable $throwable): void
    {
        $report = $this->collectorManager->toReport($throwable);
        $async = (bool) $this->config->get('exception_notify.async', true);
        $queue = (string) $this->config->get('exception_notify.queue', 'default');

        foreach ($this->resolveChannels() as $channel) {
            if (! $this->channelReady($channel)) {
                continue;
            }

            $job = new ReportExceptionJob($channel, $report);
            if ($async && $channel !== 'log') {
                ApplicationContext::getContainer()
                    ->get(DriverFactory::class)
                    ->get($queue)
                    ->push($job);
            } else {
                $job->handle();
            }
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

        $token = config(sprintf('exception_notify.channels.%s.token', $channel));

        return is_string($token) && $token !== '';
    }

    protected function createLogDriver(): LogChannel
    {
        return new LogChannel(
            (string) config('exception_notify.channels.log.channel', 'default'),
            (string) config('exception_notify.channels.log.level', 'error'),
            'log'
        );
    }

    protected function createFeiShuDriver(): FeiShuChannel
    {
        return new FeiShuChannel(
            Factory::feiShu(arrayFilterFilled([
                'token' => config('exception_notify.channels.feiShu.token'),
                'secret' => config('exception_notify.channels.feiShu.secret'),
            ])),
            'feiShu'
        );
    }

    protected function createDingTalkDriver(): DingTalkChannel
    {
        return new DingTalkChannel(
            Factory::DingTalk(arrayFilterFilled([
                'token' => config('exception_notify.channels.dingTalk.token'),
                'secret' => config('exception_notify.channels.dingTalk.secret'),
            ])),
            'dingTalk'
        );
    }

    protected function createWeWorkDriver(): WeWorkChannel
    {
        return new WeWorkChannel(
            Factory::weWork(arrayFilterFilled([
                'token' => config('exception_notify.channels.weWork.token'),
                'secret' => config('exception_notify.channels.weWork.secret'),
            ])),
            'weWork'
        );
    }
}

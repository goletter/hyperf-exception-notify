<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Jobs;

use Goletter\HyperfExceptionNotify\Channels\AbstractChannel;
use Goletter\HyperfExceptionNotify\Events\ReportedEvent;
use Goletter\HyperfExceptionNotify\Events\ReportingEvent;
use Goletter\HyperfExceptionNotify\ExceptionNotify;
use Hyperf\AsyncQueue\Job;
use Hyperf\Context\ApplicationContext;
use Hyperf\Pipeline\Pipeline;
use Throwable;

use function Goletter\Utils\event;
use function Goletter\Utils\stdoutLogger;
use function Hyperf\Config\config;

class ReportExceptionJob extends Job
{
    public function __construct(
        protected string $channelName,
        protected string $report,
    ) {
    }

    public function handle(): void
    {
        try {
            $notify = ApplicationContext::getContainer()->get(ExceptionNotify::class);
            /** @var AbstractChannel $channel */
            $channel = $notify->driver($this->channelName);
            $pipedReport = $this->pipelineReport($channel, $this->report);

            event(new ReportingEvent($channel, $pipedReport));
            $result = $channel->report($pipedReport);
            event(new ReportedEvent($channel, $result));
        } catch (Throwable $throwable) {
            stdoutLogger()->error('Exception notify failed: ' . $throwable->getMessage(), [
                'channel' => $this->channelName,
                'exception' => $throwable,
            ]);
        }
    }

    protected function pipelineReport(AbstractChannel $channel, string $report): string
    {
        $pipes = config(
            sprintf('exception_notify.channels.%s.sanitizers', $channel->getName()),
            []
        );

        if ($pipes === [] || $pipes === null) {
            return $report;
        }

        return (new Pipeline(ApplicationContext::getContainer()))
            ->send($report)
            ->through((array) $pipes)
            ->then(static fn ($report) => $report);
    }
}

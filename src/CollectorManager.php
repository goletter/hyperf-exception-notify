<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify;

use Goletter\HyperfExceptionNotify\Contracts\CollectorContract;
use Goletter\HyperfExceptionNotify\Contracts\ExceptionAwareContract;
use Goletter\HyperfExceptionNotify\Exceptions\InvalidArgumentException;
use Goletter\HyperfExceptionNotify\Support\ReportFormatter;
use Hyperf\Support\Fluent;
use Throwable;

use function Hyperf\Collection\collect;
use function Hyperf\Config\config;
use function Hyperf\Support\make;

class CollectorManager extends Fluent
{
    /**
     * @throws InvalidArgumentException
     */
    public function __construct()
    {
        $collectors = collect(config('exception_notify.collector'))
            ->map(function ($parameters, $class) {
                if (! is_array($parameters)) {
                    [$parameters, $class] = [[], $parameters];
                }

                return make($class, $parameters);
            })
            ->values()
            ->all();

        foreach ($collectors as $index => $collector) {
            $this->offsetSet($index, $collector);
        }
    }

    /**
     * @param array-key $offset
     *
     * @throws InvalidArgumentException
     */
    public function offsetSet($offset, mixed $value): void
    {
        if (! $value instanceof CollectorContract) {
            throw new InvalidArgumentException(sprintf(
                'Collector must be instance of %s',
                CollectorContract::class
            ));
        }

        $this->attributes[$offset] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(Throwable $throwable): array
    {
        return collect($this)
            ->mapWithKeys(static function (CollectorContract $collector) use ($throwable): array {
                $collector instanceof ExceptionAwareContract and $collector->setException($throwable);

                try {
                    return [$collector->name() => $collector->collect()];
                } catch (Throwable) {
                    return [$collector->name() => []];
                }
            })
            ->all();
    }

    public function toReport(Throwable $throwable): string
    {
        $payload = $this->toPayload($throwable);
        $formatter = make(ReportFormatter::class);
        $format = (string) config('exception_notify.format', 'markdown');

        return $format === 'json'
            ? $formatter->toJson($payload)
            : $formatter->toMarkdown($payload, $throwable);
    }
}

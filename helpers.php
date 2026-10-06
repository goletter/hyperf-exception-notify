<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify;

use Exception;
use Hyperf\Context\ApplicationContext;
use Throwable;

use function Hyperf\Support\value;

/**
 * @return null|string|void
 */
function var_output(mixed $expression, bool $return = false)
{
    $patterns = [
        "/array \\(\n\\)/" => '[]',
        "/array \\(\n\\s+\\)/" => '[]',
        '/array \(/' => '[',
        '/^([ ]*)\)(,?)$/m' => '$1]$2',
        "/=>[ ]?\n[ ]+\\[/" => '=> [',
        "/([ ]*)(\\'[^\\']+\\') => ([\\[\\'])/" => '$1$2 => $3',
    ];

    $export = var_export($expression, true);
    $export = preg_replace(array_keys($patterns), array_values($patterns), $export);
    if ($return) {
        return $export;
    }

    echo $export;
}

function exception_notify_report_if(mixed $condition, mixed $exception, null|array|string $channels = null): void
{
    value($condition) and exception_notify_report($exception, $channels);
}

function exception_notify_report(mixed $exception, null|array|string $channels = null): void
{
    $exception instanceof Throwable or $exception = new Exception((string) $exception);

    ApplicationContext::getContainer()->get(ExceptionNotify::class)->report($exception, $channels);
}

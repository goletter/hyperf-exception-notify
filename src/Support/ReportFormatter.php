<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Support;

use Throwable;

use function Hyperf\Collection\collect;
use function Hyperf\Config\config;

/**
 * Build a human-readable markdown report for IM bots.
 */
class ReportFormatter
{
    /**
     * @param array<string, mixed> $payload collector payload
     */
    public function toMarkdown(array $payload, ?Throwable $throwable = null): string
    {
        $title = (string) config('exception_notify.title', 'Application Exception');
        $app = (array) ($payload['Application'] ?? $payload['application'] ?? []);
        $basic = (array) ($payload['Exception Basic'] ?? $payload['ExceptionBasic'] ?? $payload['exception basic'] ?? []);
        $request = (array) ($payload['Request Basic'] ?? $payload['RequestBasic'] ?? $payload['request basic'] ?? []);
        $trace = (array) ($payload['Exception Trace'] ?? $payload['ExceptionTrace'] ?? $payload['exception trace'] ?? []);

        // Collector names come from Collector::name() — resolve flexibly.
        foreach ($payload as $key => $value) {
            if (! is_array($value)) {
                continue;
            }
            $lower = strtolower((string) $key);
            if ($basic === [] && str_contains($lower, 'exception') && str_contains($lower, 'basic')) {
                $basic = $value;
            }
            if ($request === [] && str_contains($lower, 'request') && str_contains($lower, 'basic')) {
                $request = $value;
            }
            if ($app === [] && str_contains($lower, 'application')) {
                $app = $value;
            }
            if ($trace === [] && str_contains($lower, 'trace')) {
                $trace = $value;
            }
        }

        if ($throwable !== null && $basic === []) {
            $basic = [
                'class' => $throwable::class,
                'message' => $throwable->getMessage(),
                'code' => $throwable->getCode(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ];
        }

        $lines = [
            '## ' . $title,
            '',
            '- **Time**: ' . date('Y-m-d H:i:s'),
            '- **App**: ' . ($app['name'] ?? '-'),
            '- **Env**: ' . ($app['environment'] ?? env('APP_ENV', '-')),
            '- **Exception**: ' . ($basic['class'] ?? '-'),
            '- **Message**: ' . $this->escape((string) ($basic['message'] ?? '')),
            '- **File**: `' . ($basic['file'] ?? '-') . ':' . ($basic['line'] ?? '-') . '`',
        ];

        if ($request !== []) {
            $lines[] = '- **URL**: ' . ($request['url'] ?? '-');
            $lines[] = '- **Method**: ' . ($request['method'] ?? '-');
            $lines[] = '- **IP**: ' . ($request['ip'] ?? '-');
            if (! empty($request['route'])) {
                $lines[] = '- **Route**: `' . $request['route'] . '`';
            }
            if (! empty($request['class'])) {
                $lines[] = '- **Action**: `' . $request['class'] . '@' . ($request['function'] ?? '') . '`';
            }
            if (! empty($request['duration'])) {
                $lines[] = '- **Duration**: ' . $request['duration'];
            }
        }

        $post = $payload['Request Post'] ?? $payload['RequestPost'] ?? null;
        $query = $payload['Request Query'] ?? $payload['RequestQuery'] ?? null;
        foreach ($payload as $key => $value) {
            $lower = strtolower((string) $key);
            if ($post === null && str_contains($lower, 'post')) {
                $post = $value;
            }
            if ($query === null && str_contains($lower, 'query')) {
                $query = $value;
            }
        }

        if (is_array($post) && $post !== []) {
            $lines[] = '';
            $lines[] = '### Request Post';
            $lines[] = '```json';
            $lines[] = $this->json($post);
            $lines[] = '```';
        }

        if (is_array($query) && $query !== []) {
            $lines[] = '';
            $lines[] = '### Request Query';
            $lines[] = '```json';
            $lines[] = $this->json($query);
            $lines[] = '```';
        }

        if ($trace !== []) {
            $traceLines = collect($trace)->take(15)->all();
            $lines[] = '';
            $lines[] = '### Trace';
            $lines[] = '```';
            $lines[] = implode("\n", $traceLines);
            $lines[] = '```';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function toJson(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function escape(string $value): string
    {
        return str_replace(["\n", "\r"], ' ', trim($value));
    }

    private function json(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            return '';
        }

        // Keep IM messages short.
        return mb_strlen($encoded) > 1500 ? mb_substr($encoded, 0, 1500) . "\n..." : $encoded;
    }
}

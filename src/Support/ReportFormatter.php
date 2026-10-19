<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Support;

use Hyperf\Context\Context;
use Throwable;

use function Hyperf\Config\config;

/**
 * IM 通知正文。飞书纯文本会把文件路径和 URL 识别成可点击链接。
 */
class ReportFormatter
{
    /**
     * @param array<string, mixed> $payload collector payload
     */
    public function toMarkdown(array $payload, ?Throwable $throwable = null): string
    {
        $app = $this->section($payload, 'application');
        $basic = $this->section($payload, 'exception', 'basic');
        $request = $this->section($payload, 'request', 'basic');

        if ($throwable !== null && $basic === []) {
            $basic = [
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ];
        }

        $env = (string) ($app['environment'] ?? config('app_env', '-'));
        $level = (string) config('exception_notify.level', 'Error');
        $traceId = $this->traceId();
        $file = (string) ($basic['file'] ?? '-');
        $line = (string) ($basic['line'] ?? '-');
        $url = trim((string) ($request['url'] ?? ''));
        $logFile = $this->logFile();
        $message = $this->oneLine((string) ($basic['message'] ?? ''));

        $lines = [
            '运行环境: ' . ($env !== '' ? $env : '-'),
            '异常等级: ' . ($level !== '' ? $level : 'Error'),
            '追踪号 trace_id:',
            $traceId !== '' ? $traceId : '-',
            '异常文件: ' . $file,
            '异常行号: ' . $line,
            '异常时间: ' . date('Y-m-d H:i:s'),
            '请求URL:',
            $url !== '' ? $url : '-',
            '详细日志文件: ' . $logFile . ' (请用 trace_id 检索)',
            '异常信息: ' . ($message !== '' ? $message : '-'),
        ];

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function toJson(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function section(array $payload, string $mustContain, ?string $also = null): array
    {
        foreach ($payload as $key => $value) {
            if (! is_array($value)) {
                continue;
            }
            $lower = strtolower((string) $key);
            if (! str_contains($lower, $mustContain)) {
                continue;
            }
            if ($also !== null && ! str_contains($lower, $also)) {
                continue;
            }

            return $value;
        }

        return [];
    }

    private function traceId(): string
    {
        $traceId = trim((string) Context::get('trace_id', ''));
        if ($traceId !== '') {
            return $traceId;
        }

        return trim((string) Context::get('X-Trace-Id', ''));
    }

    private function logFile(): string
    {
        $pattern = (string) config('exception_notify.log_file', 'runtime/logs/hyperf-{date}.log');

        return str_replace('{date}', date('Y-m-d'), $pattern);
    }

    private function oneLine(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}

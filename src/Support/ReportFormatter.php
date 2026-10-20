<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Support;

use Hyperf\Context\Context;
use Throwable;

use function Hyperf\Config\config;

/**
 * IM 通知正文：钉钉 / 企微用 Markdown，飞书用交互卡片.
 */
class ReportFormatter
{
    private const FEISHU_CARD_MARK = '__feishu_card__';

    /**
     * @param array<string, mixed> $payload collector payload
     */
    public function toMarkdown(array $payload, ?Throwable $throwable = null): string
    {
        $f = $this->fields($payload, $throwable);
        $title = (string) config('exception_notify.title', '异常告警');

        $lines = [
            '### 🚨 ' . $title,
            '',
            sprintf('> **环境** %s　|　**等级** %s　|　**时间** %s', $f['env'], $f['level'], $f['time']),
            '',
            '**追踪号**',
            '`' . $f['trace_id'] . '`',
            '',
            '**异常位置**',
            '`' . $f['file'] . ':' . $f['line'] . '`',
            '',
            '**请求 URL**',
            $f['url'] === '-' ? '-' : $f['url'],
            '',
            '**详细日志**',
            '`' . $f['log_file'] . '`（请用 trace_id 检索）',
            '',
            '**异常信息**',
            $f['message'],
        ];

        return implode("\n", $lines);
    }

    /**
     * 飞书机器人 interactive card，序列化为带标记的 JSON 字符串.
     *
     * @param array<string, mixed> $payload
     */
    public function toFeishuCard(array $payload, ?Throwable $throwable = null): string
    {
        $f = $this->fields($payload, $throwable);
        $title = (string) config('exception_notify.title', '异常告警');
        $template = (string) config('exception_notify.feishu_card_template', 'red');

        $urlMd = $f['url'] === '-'
            ? '-'
            : sprintf('[%s](%s)', $this->shortUrl($f['url']), $f['url']);

        $card = [
            self::FEISHU_CARD_MARK => true,
            'config' => [
                'wide_screen_mode' => true,
                'enable_forward' => true,
            ],
            'header' => [
                'template' => $template !== '' ? $template : 'red',
                'title' => [
                    'tag' => 'plain_text',
                    'content' => '🚨 ' . $this->limit($title, 40),
                ],
            ],
            'elements' => [
                [
                    'tag' => 'div',
                    'fields' => [
                        $this->cardField('运行环境', $f['env']),
                        $this->cardField('异常等级', $f['level']),
                        $this->cardField('异常时间', $f['time']),
                        $this->cardField('异常行号', (string) $f['line']),
                    ],
                ],
                ['tag' => 'hr'],
                [
                    'tag' => 'div',
                    'text' => [
                        'tag' => 'lark_md',
                        'content' => '**追踪号**\n`' . $f['trace_id'] . '`',
                    ],
                ],
                [
                    'tag' => 'div',
                    'text' => [
                        'tag' => 'lark_md',
                        'content' => '**异常文件**\n' . $this->escapeMd($f['file']),
                    ],
                ],
                [
                    'tag' => 'div',
                    'text' => [
                        'tag' => 'lark_md',
                        'content' => '**请求 URL**\n' . $urlMd,
                    ],
                ],
                [
                    'tag' => 'div',
                    'text' => [
                        'tag' => 'lark_md',
                        'content' => '**详细日志**\n`' . $this->escapeMd($f['log_file']) . '`\n请用 trace_id 检索',
                    ],
                ],
                ['tag' => 'hr'],
                [
                    'tag' => 'div',
                    'text' => [
                        'tag' => 'lark_md',
                        'content' => '**异常信息**\n' . $this->escapeMd($f['message']),
                    ],
                ],
            ],
        ];

        return (string) json_encode($card, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string, mixed> $card
     */
    public static function isFeishuCard(array $card): bool
    {
        return ! empty($card[self::FEISHU_CARD_MARK]);
    }

    /**
     * @param array<string, mixed> $card
     * @return array<string, mixed>
     */
    public static function unwrapFeishuCard(array $card): array
    {
        unset($card[self::FEISHU_CARD_MARK]);

        return $card;
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
     * @return array{
     *     env: string,
     *     level: string,
     *     time: string,
     *     trace_id: string,
     *     file: string,
     *     line: string|int,
     *     url: string,
     *     log_file: string,
     *     message: string
     * }
     */
    private function fields(array $payload, ?Throwable $throwable): array
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

        $env = trim((string) ($app['environment'] ?? config('app_env', '-')));
        $level = trim((string) config('exception_notify.level', 'Error'));
        $traceId = $this->traceId();
        $url = trim((string) ($request['url'] ?? ''));
        $message = $this->oneLine((string) ($basic['message'] ?? ''));
        $messageLimit = (int) config('exception_notify.message_max_length', 1800);

        return [
            'env' => $env !== '' ? $env : '-',
            'level' => $level !== '' ? $level : 'Error',
            'time' => date('Y-m-d H:i:s'),
            'trace_id' => $traceId !== '' ? $traceId : '-',
            'file' => (string) ($basic['file'] ?? '-'),
            'line' => $basic['line'] ?? '-',
            'url' => $url !== '' ? $url : '-',
            'log_file' => $this->logFile(),
            'message' => $this->limit($message !== '' ? $message : '-', $messageLimit),
        ];
    }

    /**
     * @return array{is_short: bool, text: array{tag: string, content: string}}
     */
    private function cardField(string $label, string $value): array
    {
        return [
            'is_short' => true,
            'text' => [
                'tag' => 'lark_md',
                'content' => '**' . $label . '**\n' . $this->escapeMd($value),
            ],
        ];
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

    private function limit(string $value, int $max): string
    {
        if ($max <= 0 || mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max) . '...';
    }

    private function escapeMd(string $value): string
    {
        return str_replace(['*', '`'], ['＊', 'ˋ'], $value);
    }

    private function shortUrl(string $url): string
    {
        return mb_strlen($url) > 80 ? mb_substr($url, 0, 80) . '...' : $url;
    }
}

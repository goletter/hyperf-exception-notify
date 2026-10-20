<?php

declare(strict_types=1);

use Goletter\HyperfExceptionNotify\Collectors\ApplicationCollector;
use Goletter\HyperfExceptionNotify\Collectors\ExceptionBasicCollector;
use Goletter\HyperfExceptionNotify\Collectors\ExceptionTraceCollector;
use Goletter\HyperfExceptionNotify\Collectors\RequestBasicCollector;
use Goletter\HyperfExceptionNotify\Collectors\RequestPostCollector;
use Goletter\HyperfExceptionNotify\Collectors\RequestQueryCollector;
use Goletter\HyperfExceptionNotify\Sanitizers\AppendContentSanitizer;
use Goletter\HyperfExceptionNotify\Sanitizers\LengthLimitSanitizer;

use function Hyperf\Support\env;

return [
    /*
    |--------------------------------------------------------------------------
    | Enable exception notification.
    |--------------------------------------------------------------------------
    */
    'enabled' => (bool) env('EXCEPTION_NOTIFY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Enable CLI / command exception notification.
    |--------------------------------------------------------------------------
    */
    'enabled_cli' => (bool) env('EXCEPTION_NOTIFY_ENABLED_CLI', true),

    /*
    |--------------------------------------------------------------------------
    | Environments that should report. Supports wildcards via Str::is().
    |--------------------------------------------------------------------------
    */
    'env' => array_filter(array_map('trim', explode(',', (string) env('EXCEPTION_NOTIFY_ENV', 'production,prod')))),

    /*
    |--------------------------------------------------------------------------
    | Exception types that should not be reported.
    |--------------------------------------------------------------------------
    */
    'dont_report' => [
        // \Hyperf\HttpMessage\Exception\NotFoundHttpException::class,
        // \Hyperf\Validation\ValidationException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Report format: markdown (IM friendly) | json
    |--------------------------------------------------------------------------
    */
    'format' => env('EXCEPTION_NOTIFY_FORMAT', 'markdown'),

    /*
    |--------------------------------------------------------------------------
    | Push IM notifications via async-queue (log channel always sync).
    |--------------------------------------------------------------------------
    */
    'async' => (bool) env('EXCEPTION_NOTIFY_ASYNC', true),
    'queue' => env('EXCEPTION_NOTIFY_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Channels that receive reports. Defaults to [default] if empty.
    | Example: ['dingTalk'] or ['feiShu', 'log']
    |--------------------------------------------------------------------------
    */
    'report_channels' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('EXCEPTION_NOTIFY_CHANNELS', env('EXCEPTION_NOTIFY_DEFAULT_CHANNEL', 'log')))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Collectors.
    |--------------------------------------------------------------------------
    */
    'collector' => [
        ApplicationCollector::class,
        ExceptionBasicCollector::class,
        ExceptionTraceCollector::class,
        RequestBasicCollector::class,
        RequestPostCollector::class,
        RequestQueryCollector::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request fields (post / query / header keys) masked as ******.
    | Case-insensitive, supports wildcards via Str::is().
    |--------------------------------------------------------------------------
    */
    'mask_fields' => [
        '*password*',
        '*token*',
        '*secret*',
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiter (same exception fingerprint).
    |--------------------------------------------------------------------------
    */
    'rate_limiter' => [
        'max_attempts' => (int) env('EXCEPTION_NOTIFY_LIMIT', in_array(env('APP_ENV'), ['prod', 'production'], true) ? 1 : 50),
        'decay_seconds' => (int) env('EXCEPTION_NOTIFY_DECAY', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Report title (json / 关键词场景仍可用；默认正文不再展示标题).
    |--------------------------------------------------------------------------
    */
    'title' => env('EXCEPTION_NOTIFY_REPORT_TITLE', sprintf('[%s] %s exception', env('APP_ENV', 'local'), env('APP_NAME', 'app'))),

    /*
    |--------------------------------------------------------------------------
    | 通知正文里的「异常等级」.
    |--------------------------------------------------------------------------
    */
    'level' => env('EXCEPTION_NOTIFY_LEVEL', 'Error'),

    /*
    |--------------------------------------------------------------------------
    | 详细日志文件路径，{date} 替换为当天 Y-m-d.
    |--------------------------------------------------------------------------
    */
    'log_file' => env('EXCEPTION_NOTIFY_LOG_FILE', 'runtime/logs/hyperf-{date}.log'),

    /*
    |--------------------------------------------------------------------------
    | 异常信息最大长度（超出截断）.
    |--------------------------------------------------------------------------
    */
    'message_max_length' => (int) env('EXCEPTION_NOTIFY_MESSAGE_MAX', 1800),

    /*
    |--------------------------------------------------------------------------
    | 飞书卡片标题颜色：red / orange / yellow / green / blue / purple / grey
    |--------------------------------------------------------------------------
    */
    'feishu_card_template' => env('EXCEPTION_NOTIFY_FEISHU_CARD_TEMPLATE', 'red'),

    /*
    |--------------------------------------------------------------------------
    | Default channel name (used when report_channels is empty).
    |--------------------------------------------------------------------------
    */
    'default' => env('EXCEPTION_NOTIFY_DEFAULT_CHANNEL', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Channel definitions.
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'log' => [
            'driver' => 'log',
            'channel' => env('EXCEPTION_NOTIFY_LOG_CHANNEL', 'default'),
            'level' => env('EXCEPTION_NOTIFY_LOG_LEVEL', 'error'),
            'sanitizers' => [],
        ],

        /**
         * 飞书群机器人.
         * @see https://www.feishu.cn/hc/zh-CN/articles/360024984973
         */
        'feiShu' => [
            'driver' => 'feiShu',
            'token' => env('EXCEPTION_NOTIFY_FEISHU_TOKEN'),
            'secret' => env('EXCEPTION_NOTIFY_FEISHU_SECRET'),
            'keyword' => env('EXCEPTION_NOTIFY_FEISHU_KEYWORD'),
            // 交互卡片不要用字符串截断 / 追加，关键词由 FeiShuChannel 写入 note
            'sanitizers' => [],
        ],

        /**
         * 钉钉群机器人.
         * @see https://developers.dingtalk.com/document/app/custom-robot-access
         */
        'dingTalk' => [
            'driver' => 'dingTalk',
            'token' => env('EXCEPTION_NOTIFY_DINGTALK_TOKEN'),
            'secret' => env('EXCEPTION_NOTIFY_DINGTALK_SECRET'),
            'keyword' => env('EXCEPTION_NOTIFY_DINGTALK_KEYWORD'),
            'atMobiles' => [],
            'atDingtalkIds' => [],
            'isAtAll' => false,
            // Truncate first, then append the keyword, so the required keyword is never cut off.
            'sanitizers' => array_values(array_filter([
                sprintf('%s:%s', LengthLimitSanitizer::class, 20000),
                env('EXCEPTION_NOTIFY_DINGTALK_KEYWORD')
                    ? sprintf('%s:%s', AppendContentSanitizer::class, env('EXCEPTION_NOTIFY_DINGTALK_KEYWORD'))
                    : null,
            ])),
        ],

        /**
         * 企业微信群机器人.
         * @see https://open.work.weixin.qq.com/api/doc/90000/90136/91770
         */
        'weWork' => [
            'driver' => 'weWork',
            'token' => env('EXCEPTION_NOTIFY_WEWORK_TOKEN'),
            'sanitizers' => [
                sprintf('%s:%s', LengthLimitSanitizer::class, 4096),
            ],
        ],
    ],
];

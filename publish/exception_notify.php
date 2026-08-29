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
    | Rate limiter (same exception fingerprint).
    |--------------------------------------------------------------------------
    */
    'rate_limiter' => [
        'max_attempts' => (int) env('EXCEPTION_NOTIFY_LIMIT', env('APP_ENV') === 'prod' || env('APP_ENV') === 'production' ? 1 : 50),
        'decay_seconds' => (int) env('EXCEPTION_NOTIFY_DECAY', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Report title.
    |--------------------------------------------------------------------------
    */
    'title' => env('EXCEPTION_NOTIFY_REPORT_TITLE', sprintf('[%s] %s exception', env('APP_ENV', 'local'), env('APP_NAME', 'app'))),

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
            'sanitizers' => array_values(array_filter([
                sprintf('%s:%s', LengthLimitSanitizer::class, 30720),
                env('EXCEPTION_NOTIFY_FEISHU_KEYWORD')
                    ? sprintf('%s:%s', AppendContentSanitizer::class, env('EXCEPTION_NOTIFY_FEISHU_KEYWORD'))
                    : null,
            ])),
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
            'sanitizers' => array_values(array_filter([
                env('EXCEPTION_NOTIFY_DINGTALK_KEYWORD')
                    ? sprintf('%s:%s', AppendContentSanitizer::class, env('EXCEPTION_NOTIFY_DINGTALK_KEYWORD'))
                    : null,
                sprintf('%s:%s', LengthLimitSanitizer::class, 20000),
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

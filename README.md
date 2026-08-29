# hyperf-exception-notify

Hyperf 异常通知组件：异常发生后推送到 **日志 / 钉钉 / 企业微信 / 飞书**。

## 环境要求

- PHP >= 8.2
- Hyperf >= 3.1
- Redis（限流）
- 可选：`hyperf/async-queue` Worker（IM 异步推送）

## 安装

```bash
composer require goletter/hyperf-exception-notify
php bin/hyperf.php vendor:publish goletter/hyperf-exception-notify
```

在 `config/autoload/exceptions.php` 注册 Handler（靠后一点，避免挡住业务 Handler）：

```php
return [
    'handler' => [
        'http' => [
            // ...
            \Goletter\HyperfExceptionNotify\Exceptions\Handler\ExceptionNotifyHandler::class,
        ],
    ],
];
```

CLI 命令异常已通过 `CommandFailToHandleListener` 自动监听（可用 `enabled_cli` 关闭）。

## 配置要点

`.env` 示例（钉钉）：

```dotenv
EXCEPTION_NOTIFY_ENABLED=true
EXCEPTION_NOTIFY_ENV=production,prod
EXCEPTION_NOTIFY_CHANNELS=dingTalk
EXCEPTION_NOTIFY_DEFAULT_CHANNEL=dingTalk
EXCEPTION_NOTIFY_FORMAT=markdown
EXCEPTION_NOTIFY_ASYNC=true
EXCEPTION_NOTIFY_QUEUE=default
EXCEPTION_NOTIFY_LIMIT=1
EXCEPTION_NOTIFY_DECAY=300

EXCEPTION_NOTIFY_DINGTALK_TOKEN=your_access_token
EXCEPTION_NOTIFY_DINGTALK_SECRET=your_secret
EXCEPTION_NOTIFY_DINGTALK_KEYWORD=异常
EXCEPTION_NOTIFY_REPORT_TITLE=[prod] my-app exception
```

| 配置 | 说明 |
|------|------|
| `report_channels` / `EXCEPTION_NOTIFY_CHANNELS` | **实际推送的渠道**（逗号分隔）。不会再把所有 channels 定义都推一遍 |
| `format=markdown` | IM 可读的 Markdown 摘要（默认）；`json` 为原始采集 JSON |
| `async=true` | IM 走异步队列；`log` 仍同步 |
| 空 `token` | 自动跳过该 IM 渠道，避免无效请求 |
| 限流 | 按「异常类+文件+行+消息」指纹限流，同异常短时间不刷屏 |

## 手动上报

```php
use function Goletter\HyperfExceptionNotify\exception_notify_report;
use function Goletter\HyperfExceptionNotify\exception_notify_report_if;

exception_notify_report($throwable);                 // 走配置的 report_channels
exception_notify_report($throwable, 'dingTalk');     // 只推钉钉
exception_notify_report($throwable, ['feiShu', 'log']);

exception_notify_report_if(
    fn () => $order->isPaid(),
    $throwable,
    'weWork'
);
```

或注入：

```php
use Goletter\HyperfExceptionNotify\ExceptionNotify;

public function __construct(private ExceptionNotify $notify) {}

$this->notify->onChannel('dingTalk')->report($e);
```

## 通知长什么样

默认 Markdown 大致包含：

- 标题 / 时间 / 应用 / 环境
- 异常类、消息、文件行号
- 请求 URL、Method、IP、路由、耗时
- 部分 Post / Query
- Trace（过滤 vendor，截断）

钉钉、企业微信使用 Markdown 消息；飞书使用文本（可拼 keyword）。

## 支持渠道

| 渠道 | driver |
|------|--------|
| 控制台/日志 | `log` |
| 钉钉机器人 | `dingTalk` |
| 企业微信机器人 | `weWork` |
| 飞书机器人 | `feiShu` |

## 升级注意（相对旧版）

1. **不再默认推送全部 channels**，只推 `EXCEPTION_NOTIFY_CHANNELS` / `report_channels`
2. 默认环境改为 `production,prod`（本地默认不推，需要时设 `EXCEPTION_NOTIFY_ENV=*`）
3. 修复 WeWork 驱动方法名、渠道名、配置 key（`exception_notify`）、`pipes` → `sanitizers`
4. IM 默认异步队列；请确保 queue consumer 在跑
5. `composer.json` ConfigProvider 命名空间已修正为 `Goletter\HyperfExceptionNotify\ConfigProvider`

重新发布配置后，请对照合并你的旧 `exception_notify.php`。

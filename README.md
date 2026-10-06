# hyperf-exception-notify

Hyperf 异常通知组件：线上出现异常时，自动把异常信息、请求参数、堆栈推送到 **钉钉 / 企业微信 / 飞书** 群机器人，或写入日志。

- HTTP 请求异常、命令行异常自动上报，业务代码也可手动上报
- 同一个异常短时间内只推一次，不刷屏
- 默认异步队列推送，不拖慢接口
- 自动对密码、token、Authorization 等敏感字段打码

## 环境要求

- PHP >= 8.2
- Hyperf >= 3.1
- Redis（用于限流）
- `hyperf/async-queue` 消费进程（异步推送时需要）

## 一、安装

```bash
composer require goletter/hyperf-exception-notify
php bin/hyperf.php vendor:publish goletter/hyperf-exception-notify
```

发布后生成配置文件 `config/autoload/exception_notify.php`。

## 二、三步接入（以钉钉为例）

### 1. 创建群机器人

钉钉群 → 群设置 → 机器人 → 添加「自定义」机器人，安全设置任选：

- **加签**：复制 `SEC` 开头的密钥，填到 `EXCEPTION_NOTIFY_DINGTALK_SECRET`
- **自定义关键词**：例如填 `异常`，同时填到 `EXCEPTION_NOTIFY_DINGTALK_KEYWORD`

复制 Webhook 地址里 `access_token=` 后面的部分，填到 `EXCEPTION_NOTIFY_DINGTALK_TOKEN`。

### 2. 配置 `.env`

```dotenv
EXCEPTION_NOTIFY_CHANNELS=dingTalk
EXCEPTION_NOTIFY_DINGTALK_TOKEN=xxxxxxxx
EXCEPTION_NOTIFY_DINGTALK_SECRET=SECxxxxxxxx
EXCEPTION_NOTIFY_DINGTALK_KEYWORD=异常
```

> 默认只在 `APP_ENV` 为 `production` / `prod` 时推送。本地调试见「七、本地测试」。

### 3. 注册异常处理器

`config/autoload/exceptions.php`，**放在第一个**：

```php
return [
    'handler' => [
        'http' => [
            \Goletter\HyperfExceptionNotify\Exceptions\Handler\ExceptionNotifyHandler::class,
            \App\Exception\Handler\AppExceptionHandler::class,
            // ...
        ],
    ],
];
```

Hyperf 按顺序执行异常处理器，前面的处理器一旦调用 `stopPropagation()`，后面的就不会执行。`ExceptionNotifyHandler` 只负责上报，不修改响应、不阻止后续处理器，放在第一个才能保证每个异常都经过它。

完成。命令行（`php bin/hyperf.php xxx`）执行失败也会自动上报，无需额外配置。

## 三、各平台配置

可以同时推多个平台：`EXCEPTION_NOTIFY_CHANNELS=dingTalk,weWork,log`。没填 `token` 的平台会被自动跳过。

| 平台 | 渠道名 | 环境变量 | 消息格式 | 长度上限 |
| --- | --- | --- | --- | --- |
| 钉钉 | `dingTalk` | `EXCEPTION_NOTIFY_DINGTALK_TOKEN` / `_SECRET` / `_KEYWORD` | Markdown | 20000 |
| 企业微信 | `weWork` | `EXCEPTION_NOTIFY_WEWORK_TOKEN` | Markdown | 4096 字节 |
| 飞书 | `feiShu` | `EXCEPTION_NOTIFY_FEISHU_TOKEN` / `_SECRET` / `_KEYWORD` | 纯文本 | 30720 |
| 日志 | `log` | `EXCEPTION_NOTIFY_LOG_LEVEL` | 输出到控制台 | 无 |

- **token**：Webhook 地址中的 key。钉钉是 `access_token=` 后面的值；企业微信是 `key=` 后面的值；飞书是 `/hook/` 后面的值。
- **超长截断**：实际按上限的 90% 截断，结尾显示 `...`。企业微信上限较小，Post 参数和堆栈经常被截断。
- **关键词**：钉钉、飞书设置了关键词安全校验时必须配置 `_KEYWORD`，会自动追加在消息末尾。
- **@人（钉钉）**：在配置文件 `channels.dingTalk` 中设置 `atMobiles`、`atDingtalkIds` 或 `isAtAll`。

## 四、通知内容示例

````text
## [production] my-app exception

- **Time**: 2026-10-06 22:45:12
- **App**: my-app
- **Env**: production
- **Exception**: RuntimeException
- **Message**: 库存不足，商品ID=1024
- **File**: `/opt/www/app/Service/OrderService.php:88`
- **URL**: https://api.example.com/v1/orders?page=1
- **Method**: POST
- **IP**: 113.88.12.34
- **Route**: `/v1/orders`
- **Action**: `App\Controller\OrderController@store`
- **Duration**: 35ms

### Request Post
```json
{
    "goods_id": 1024,
    "password": "******"
}
```

### Trace
```
#0 /opt/www/app/Controller/OrderController.php(42): App\Service\OrderService->create(Array)
#9 {main}
```
异常
````

- 命令行异常没有请求信息，URL、Post、Query 等部分不显示。
- 堆栈会过滤掉 `vendor` 目录，只保留业务代码，最多 15 行。
- Post / Query 每块最多约 1500 字符。

## 五、配置说明

所有配置都在 `config/autoload/exception_notify.php`，大部分可以用环境变量覆盖。

### 开关与范围

| 配置项 | 环境变量 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `enabled` | `EXCEPTION_NOTIFY_ENABLED` | `true` | 总开关 |
| `enabled_cli` | `EXCEPTION_NOTIFY_ENABLED_CLI` | `true` | 命令行异常是否上报 |
| `env` | `EXCEPTION_NOTIFY_ENV` | `production,prod` | 哪些环境推送，逗号分隔，支持通配符，`*` 表示所有环境 |
| `dont_report` | - | `[]` | 不上报的异常类（含子类） |
| `report_channels` | `EXCEPTION_NOTIFY_CHANNELS` | `log` | 推送到哪些渠道，逗号分隔 |

常见的不需要上报的异常：

```php
'dont_report' => [
    \Hyperf\HttpMessage\Exception\NotFoundHttpException::class,
    \Hyperf\HttpMessage\Exception\MethodNotAllowedHttpException::class,
    \Hyperf\Validation\ValidationException::class,
    \App\Exception\BusinessException::class,
],
```

### 推送方式

| 配置项 | 环境变量 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `async` | `EXCEPTION_NOTIFY_ASYNC` | `true` | 是否通过异步队列推送；`log` 渠道始终同步 |
| `queue` | `EXCEPTION_NOTIFY_QUEUE` | `default` | 使用的队列池，对应 `config/autoload/async_queue.php` |
| `format` | `EXCEPTION_NOTIFY_FORMAT` | `markdown` | `markdown` 为可读摘要；`json` 为全部采集数据 |
| `title` | `EXCEPTION_NOTIFY_REPORT_TITLE` | `[环境] 应用名 exception` | 消息标题 |

- 异步推送需要队列消费进程在运行，否则消息会积压在队列里。
- 队列本身不可用（如推送到 Redis 失败）时，会自动改为同步发送。

### 限流

| 配置项 | 环境变量 | 默认值 | 说明 |
| --- | --- | --- | --- |
| `rate_limiter.max_attempts` | `EXCEPTION_NOTIFY_LIMIT` | 生产 `1`，其它 `50` | 时间窗口内同一异常最多推送次数 |
| `rate_limiter.decay_seconds` | `EXCEPTION_NOTIFY_DECAY` | `300` | 时间窗口（秒） |

「同一异常」指异常类、文件、行号、消息完全相同。消息里带变量（如订单号）时，每条都会被视为不同异常。

### 敏感字段打码

```php
'mask_fields' => [
    '*password*',
    '*token*',
    '*secret*',
    'authorization',
    'cookie',
    'set-cookie',
    'x-api-key',
],
```

对 Post、Query、Header 生效，不区分大小写，支持 `*` 通配符，嵌套数组也会处理。可按业务追加，例如 `'id_card'`、`'*mobile*'`。

## 六、手动上报

### 帮助函数

```php
use function Goletter\HyperfExceptionNotify\exception_notify_report;
use function Goletter\HyperfExceptionNotify\exception_notify_report_if;

try {
    $this->payService->refund($order);
} catch (\Throwable $e) {
    exception_notify_report($e);                       // 推送到配置的渠道
    exception_notify_report($e, 'dingTalk');           // 只推钉钉
    exception_notify_report($e, ['weWork', 'log']);    // 推多个渠道
}

// 也可以直接传字符串
exception_notify_report('第三方回调签名校验失败：order_id=' . $orderId);

// 条件成立才上报，条件可以是闭包
exception_notify_report_if($order->amount > 10000, $e, 'dingTalk');
```

### 依赖注入

```php
use Goletter\HyperfExceptionNotify\ExceptionNotify;

public function __construct(private ExceptionNotify $notify) {}

$this->notify->report($e);
$this->notify->report($e, 'dingTalk');
$this->notify->onChannel('dingTalk')->report($e);
$this->notify->reportIf($condition, $e);
```

手动上报同样受 `enabled`、`env`、`dont_report`、限流约束。

### 上报队列任务、定时任务的异常

默认只自动上报 HTTP 请求和命令行的异常。异步队列任务、定时任务执行失败时，可以加一个监听器：

```php
namespace App\Listener;

use Goletter\HyperfExceptionNotify\ExceptionNotify;
use Hyperf\AsyncQueue\Event\FailedHandle;
use Hyperf\Crontab\Event\FailToExecute;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;

#[Listener]
class ReportJobFailureListener implements ListenerInterface
{
    public function __construct(private ExceptionNotify $notify) {}

    public function listen(): array
    {
        return [FailedHandle::class, FailToExecute::class];
    }

    public function process(object $event): void
    {
        match (true) {
            $event instanceof FailedHandle => $this->notify->report($event->getThrowable()),
            $event instanceof FailToExecute => $this->notify->report($event->throwable),
            default => null,
        };
    }
}
```

## 七、本地测试

`.env` 临时改为：

```dotenv
EXCEPTION_NOTIFY_ENV=*
EXCEPTION_NOTIFY_ASYNC=false
EXCEPTION_NOTIFY_LIMIT=100
```

加一个测试路由：

```php
Router::get('/test/exception', function () {
    throw new \RuntimeException('异常通知测试');
});
```

访问后群里应收到消息。测试完记得把配置改回来。

收不到消息时按顺序检查：

1. `APP_ENV` 是否在 `EXCEPTION_NOTIFY_ENV` 中
2. `EXCEPTION_NOTIFY_CHANNELS` 是否包含该平台，`token` 是否已填写
3. 异常是否在 `dont_report` 中，或者是否被其它异常处理器提前 `stopPropagation()`（处理器要放第一个）
4. 是否被限流：同一异常在 `decay_seconds` 内已推送过
5. 异步模式下队列消费进程是否在运行
6. 机器人安全设置：关键词是否一致、加签密钥是否正确、IP 白名单是否包含服务器出口 IP
7. 查看控制台日志中的 `Exception notify failed` 错误信息

## 八、进阶

### 事件

每次推送前后会分发事件，可用于统计或审计：

| 事件 | 时机 | 属性 |
| --- | --- | --- |
| `Goletter\HyperfExceptionNotify\Events\ReportingEvent` | 推送前 | `channel`、`report`（最终消息内容） |
| `Goletter\HyperfExceptionNotify\Events\ReportedEvent` | 推送后 | `channel`、`result`（平台返回结果） |

### 采集器

配置项 `collector` 决定采集哪些信息：

| 采集器 | 内容 | 默认启用 |
| --- | --- | --- |
| `ApplicationCollector` | 应用名、版本、环境 | 是 |
| `ExceptionBasicCollector` | 异常类、消息、代码、文件行号 | 是 |
| `ExceptionTraceCollector` | 堆栈（过滤 vendor） | 是 |
| `RequestBasicCollector` | URL、方法、IP、路由、耗时 | 是 |
| `RequestPostCollector` | Post 参数（打码） | 是 |
| `RequestQueryCollector` | Query 参数（打码） | 是 |
| `RequestHeaderCollector` | 请求头（打码） | 否 |
| `ExceptionContextCollector` | 出错位置前后的源码 | 否 |
| `RequestMiddlewareCollector` | 路由中间件 | 否 |
| `RequestFileCollector` | 上传文件信息 | 否 |
| `RequestCookieCollector` | Cookie（**不打码**） | 否 |
| `RequestSessionCollector` | Session（**不打码**） | 否 |
| `RequestServerCollector` | Server 参数 | 否 |
| `ChoreCollector` | 时间、内存峰值 | 否 |
| `PhpInfoCollector` | PHP 版本、SAPI | 否 |

**注意**：`markdown` 格式只展示前 6 个采集器的内容。其它采集器（包括自定义采集器）的数据只在 `format=json` 时出现。

自定义采集器：

```php
use Goletter\HyperfExceptionNotify\Collectors\Collector;

class TenantCollector extends Collector
{
    public function collect(): array
    {
        return ['tenant_id' => \Hyperf\Context\Context::get('tenant_id')];
    }
}
```

需要拿到异常对象时，实现 `ExceptionAwareContract` 并使用 `ExceptionAwareTrait`，通过 `$this->exception` 访问。

### 消息处理管道（sanitizers）

每个渠道的 `sanitizers` 会在发送前依次处理消息内容，参数写在冒号后面，多个参数用逗号分隔：

```php
'sanitizers' => [
    LengthLimitSanitizer::class . ':4096',           // 截断到 4096 × 90%
    StrReplaceSanitizer::class . ':/opt/www/,',      // 去掉路径前缀
    AppendContentSanitizer::class . ':@所有人',       // 末尾追加内容
],
```

可用的处理器：`LengthLimitSanitizer`、`AppendContentSanitizer`、`PrependContentSanitizer`、`StrReplaceSanitizer`、`TrimSanitizer`、`ToMarkdownSanitizer`、`ToHtmlSanitizer`、`UrlEncodeSanitizer`、`FixPrettyJsonSanitizer`、`VarOutputSanitizer`。

截断要放在追加关键词**之前**，否则关键词可能被截掉，导致机器人拒收。

## 九、升级注意（相对旧版）

1. **不再默认推送全部渠道**，只推 `EXCEPTION_NOTIFY_CHANNELS` / `report_channels` 中的渠道
2. 默认环境改为 `production,prod`，本地默认不推送
3. 修复 WeWork 驱动方法名、渠道名、配置 key（`exception_notify`）、`pipes` 改名为 `sanitizers`
4. 推送默认走异步队列，请确保队列消费进程在运行
5. `composer.json` 中 ConfigProvider 命名空间已修正为 `Goletter\HyperfExceptionNotify\ConfigProvider`
6. 新增 `mask_fields` 配置；钉钉 `sanitizers` 顺序改为「先截断、再追加关键词」
7. `ExceptionNotifyHandler` 建议放在异常处理器列表的第一个

重新发布配置后，请对照合并旧的 `exception_notify.php`。

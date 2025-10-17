# hyperf-exception-notify

## 环境要求

* PHP >= 8.2
* hyperf >= 3.1

## 安装

```shell
composer require goletter/hyperf-exception-notify -vvv
```

## 配置

```shell
php bin/hyperf.php vendor:publish goletter/hyperf-exception-notify
```

## 支持通知

```shell
log 控制台日志
dingTalk 钉钉群机器人
weWork 企业微信群机器人
feiShu 飞书群机器人
```

## 使用案例

```shell
use function Goletter\HyperfExceptionNotify\exception_notify_report;
use function Goletter\HyperfExceptionNotify\exception_notify_report_if;
# 异常通知报告
exception_notify_report($throwable, 'log');
# 是否满足条件，如果满足条件，则报告
exception_notify_report_if(function(){ return true; }, $throwable, 'log');
```
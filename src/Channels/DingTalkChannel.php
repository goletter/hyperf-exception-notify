<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Channels;

use Guanguans\Notify\Contracts\MessageInterface;
use Guanguans\Notify\Messages\DingTalk\MarkdownMessage;

use function Goletter\Utils\arrayFilterFilled;
use function Hyperf\Config\config;

class DingTalkChannel extends NotifyAbstractChannel
{
    protected function createMessage(string $report): MessageInterface
    {
        $title = (string) config('exception_notify.title', 'Exception');

        return MarkdownMessage::create(arrayFilterFilled([
            'title' => $title,
            'text' => $report,
            'atMobiles' => config('exception_notify.channels.dingTalk.atMobiles'),
            'atDingtalkIds' => config('exception_notify.channels.dingTalk.atDingtalkIds'),
            'isAtAll' => config('exception_notify.channels.dingTalk.isAtAll'),
        ]));
    }
}

<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Channels;

use Guanguans\Notify\Contracts\MessageInterface;
use Guanguans\Notify\Messages\WeWork\MarkdownMessage;

use function Hyperf\Config\config;

class WeWorkChannel extends NotifyAbstractChannel
{
    protected function createMessage(string $report): MessageInterface
    {
        // WeWork markdown content; mentioned lists are text-message only.
        return MarkdownMessage::create([
            'content' => $report,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Channels;

use Guanguans\Notify\Contracts\MessageInterface;
use Guanguans\Notify\Messages\FeiShu\TextMessage;

class FeiShuChannel extends NotifyAbstractChannel
{
    protected function createMessage(string $report): MessageInterface
    {
        return new TextMessage($report);
    }
}

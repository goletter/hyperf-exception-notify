<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Channels;

use Guanguans\Notify\Clients\Client;
use Guanguans\Notify\Contracts\MessageInterface;

abstract class NotifyAbstractChannel extends AbstractChannel
{
    public function __construct(
        protected Client $client,
        string $name = ''
    ) {
        parent::__construct($name);
    }

    public function report(string $report)
    {
        return $this->client->send($this->createMessage($report));
    }

    abstract protected function createMessage(string $report): MessageInterface;
}

<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Channels;

use Goletter\HyperfExceptionNotify\Contracts\ChannelContract;

abstract class AbstractChannel implements ChannelContract
{
    public function __construct(
        protected string $name = ''
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    abstract public function report(string $report);
}

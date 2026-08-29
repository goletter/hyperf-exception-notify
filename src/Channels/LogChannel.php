<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Channels;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use function Goletter\Utils\stdoutLogger;

class LogChannel extends AbstractChannel
{
    public function __construct(
        protected string $channel,
        protected string $level,
        string $name = 'log'
    ) {
        parent::__construct($name);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function report(string $report): void
    {
        stdoutLogger()->{$this->level}($report);
    }
}

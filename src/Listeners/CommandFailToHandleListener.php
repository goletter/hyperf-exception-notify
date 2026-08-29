<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Listeners;

use Goletter\HyperfExceptionNotify\ExceptionNotify;
use Hyperf\Command\Event\FailToHandle;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Event\Contract\ListenerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use function Hyperf\Config\config;

class CommandFailToHandleListener implements ListenerInterface
{
    #[Inject]
    protected ExceptionNotify $exceptionNotify;

    public function listen(): array
    {
        return [
            FailToHandle::class,
        ];
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(object $event): void
    {
        if (! config('exception_notify.enabled_cli', true)) {
            return;
        }

        if (! $event instanceof FailToHandle) {
            return;
        }

        $this->exceptionNotify->report($event->getThrowable());
    }
}

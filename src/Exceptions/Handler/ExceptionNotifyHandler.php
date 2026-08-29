<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Exceptions\Handler;

use Goletter\HyperfExceptionNotify\ExceptionNotify;
use Hyperf\Di\Annotation\Inject;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ExceptionNotifyHandler extends ExceptionHandler
{
    #[Inject]
    protected ExceptionNotify $exceptionNotify;

    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        // Uses report_channels / default — do NOT blast every configured channel key.
        $this->exceptionNotify->report($throwable);

        return $response;
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}

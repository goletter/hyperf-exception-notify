<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Goletter\HyperfExceptionNotify\Collectors;

use Goletter\HyperfExceptionNotify\Contracts\ExceptionAwareContract;
use Goletter\HyperfExceptionNotify\Support\ExceptionContext;
use Goletter\HyperfExceptionNotify\Traits\ExceptionAwareTrait;

class ExceptionBasicCollector extends Collector implements ExceptionAwareContract
{
    use ExceptionAwareTrait;

    /** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
    public function collect(): array
    {
        return [
            'class' => get_class($this->exception),
            'message' => $this->exception->getMessage(),
            'code' => $this->exception->getCode(),
            'file' => $this->exception->getFile(),
            'line' => $this->exception->getLine(),
            'preview' => ExceptionContext::getFormattedContext($this->exception),
        ];
    }
}

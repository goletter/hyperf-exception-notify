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
use Goletter\HyperfExceptionNotify\Traits\ExceptionAwareTrait;
use Hyperf\Stringable\Str;

use function Hyperf\Collection\collect;

class ExceptionTraceCollector extends Collector implements ExceptionAwareContract
{
    use ExceptionAwareTrait;

    /**
     * @return string[]
     */
    public function collect(): array
    {
        return collect(explode("\n", $this->exception->getTraceAsString()))
            ->filter(static fn ($trace) => ! Str::contains($trace, 'vendor'))
            ->all();
    }
}

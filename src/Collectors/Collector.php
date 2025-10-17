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

use Goletter\HyperfExceptionNotify\Contracts\CollectorContract;
use Hyperf\Stringable\Str;

use function Hyperf\Support\class_basename;

abstract class Collector implements CollectorContract
{
    public function name(): string
    {
        return ucwords(Str::snake(Str::beforeLast(class_basename($this), 'Collector'), ' '));
    }
}

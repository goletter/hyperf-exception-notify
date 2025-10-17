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

namespace Goletter\HyperfExceptionNotify\Sanitizers;

use Closure;

use function Goletter\HyperfExceptionNotify\var_output;

class VarOutputSanitizer
{
    public function handle(string $report, Closure $next): string
    {
        return $next(var_output($report, true));
    }
}

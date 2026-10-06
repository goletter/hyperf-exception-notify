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

class LengthLimitSanitizer
{
    /**
     * @param int|string $length byte limit of the target channel; 90% of it is used as headroom
     */
    public function handle(string $report, Closure $next, $length = -1): string
    {
        $limit = (int) ((int) $length * 90 / 100);
        if ($limit > 0 && strlen($report) > $limit) {
            $report = $this->truncate($report, $limit);
        }

        return $next($report);
    }

    protected function truncate(string $report, int $limit): string
    {
        $suffix = "\n...";
        $fence = "\n```";
        $cut = mb_strcut($report, 0, max(0, $limit - strlen($suffix . $fence)), 'UTF-8');

        // Close a markdown code block left open by the cut, or the rest of the message renders as code.
        $closing = substr_count($cut, '```') % 2 === 1 ? $fence : '';

        return $cut . $suffix . $closing;
    }
}

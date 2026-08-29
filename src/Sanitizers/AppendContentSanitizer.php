<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Sanitizers;

use Closure;

class AppendContentSanitizer
{
    public function handle(string $report, Closure $next, ?string $content = null): string
    {
        if ($content !== null && $content !== '') {
            $report .= $content;
        }

        return $next($report);
    }
}

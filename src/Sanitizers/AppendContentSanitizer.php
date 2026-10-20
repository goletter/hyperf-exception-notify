<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Sanitizers;

use Closure;

class AppendContentSanitizer
{
    public function handle(string $report, Closure $next, ?string $content = null): string
    {
        $content = $this->normalize($content);
        if ($content === null) {
            return $next($report);
        }

        // 正文里已有关键词则不再追加，避免消息末尾多出「异常」一行
        if (str_contains($report, $content)) {
            return $next($report);
        }

        return $next($report . "\n" . $content);
    }

    private function normalize(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        $content = trim($content);
        if ($content === '' || in_array(strtolower($content), ['null', 'none', 'false'], true)) {
            return null;
        }

        return $content;
    }
}

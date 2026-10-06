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

use function Hyperf\Config\config;
use function Hyperf\Support\class_basename;

abstract class Collector implements CollectorContract
{
    public const DEFAULT_MASK_FIELDS = [
        '*password*',
        '*token*',
        '*secret*',
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
    ];

    public function name(): string
    {
        return ucwords(Str::snake(Str::beforeLast(class_basename($this), 'Collector'), ' '));
    }

    /**
     * Recursively replace values whose key matches exception_notify.mask_fields (case-insensitive).
     */
    protected function mask(array $data): array
    {
        $patterns = array_map(
            'strtolower',
            (array) config('exception_notify.mask_fields', self::DEFAULT_MASK_FIELDS)
        );

        foreach ($data as $key => $value) {
            if (is_string($key) && Str::is($patterns, strtolower($key))) {
                $data[$key] = '******';
            } elseif (is_array($value)) {
                $data[$key] = $this->mask($value);
            }
        }

        return $data;
    }
}

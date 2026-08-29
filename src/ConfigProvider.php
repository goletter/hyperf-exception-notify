<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify;

use Goletter\HyperfExceptionNotify\Listeners\CommandFailToHandleListener;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
            ],
            'listeners' => [
                CommandFailToHandleListener::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for goletter hyperf-exception-notify.',
                    'source' => __DIR__ . '/../publish/exception_notify.php',
                    'destination' => BASE_PATH . '/config/autoload/exception_notify.php',
                ],
            ],
        ];
    }
}

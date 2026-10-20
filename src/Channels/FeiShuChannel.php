<?php

declare(strict_types=1);

namespace Goletter\HyperfExceptionNotify\Channels;

use Goletter\HyperfExceptionNotify\Support\ReportFormatter;
use Guanguans\Notify\Contracts\MessageInterface;
use Guanguans\Notify\Messages\FeiShu\CardMessage;
use Guanguans\Notify\Messages\FeiShu\TextMessage;

use function Hyperf\Config\config;

class FeiShuChannel extends NotifyAbstractChannel
{
    protected function createMessage(string $report): MessageInterface
    {
        $decoded = json_decode($report, true);
        if (is_array($decoded) && ReportFormatter::isFeishuCard($decoded)) {
            $card = ReportFormatter::unwrapFeishuCard($decoded);
            $keyword = trim((string) config('exception_notify.channels.feiShu.keyword', ''));
            if ($keyword !== '') {
                $card['elements'][] = [
                    'tag' => 'note',
                    'elements' => [
                        [
                            'tag' => 'plain_text',
                            'content' => $keyword,
                        ],
                    ],
                ];
            }

            return new CardMessage($card);
        }

        return new TextMessage($report);
    }
}

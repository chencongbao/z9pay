<?php

namespace App\Extendtions\Telegram;

use App\Traits\TelegramTrait;
use App\Jobs\TodayMerchantCentusJob;

class todayMerchantCentusAction
{
    use TelegramTrait;

    protected $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = []): void
    {
        if (trim((string)($message['text'] ?? '')) !== '今日统计') {
            return;
        }

        $merchantUserId = intval($this->getMerchantUserId($message));
        if ($merchantUserId <= 0) {
            return;
        }

        TodayMerchantCentusJob::dispatch($merchantUserId, $message)->onQueue('query');
    }
}

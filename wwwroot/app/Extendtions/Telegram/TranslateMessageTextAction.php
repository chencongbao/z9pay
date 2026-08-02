<?php

namespace App\Extendtions\Telegram;

use App\Jobs\TranslateTelegramMessageJob;

class TranslateMessageTextAction
{
    public function excute($message = [], $text = ''): void
    {
        $text = trim((string)$text);
        if ($text === '') {
            return;
        }

        TranslateTelegramMessageJob::dispatch($message, 'en-US', $text)->onQueue('query');
    }
}

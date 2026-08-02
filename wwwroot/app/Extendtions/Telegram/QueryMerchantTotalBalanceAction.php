<?php

namespace App\Extendtions\Telegram;

use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Merchant\GetMerchantTotalBalanceAmountService;

class QueryMerchantTotalBalanceAction
{
    use TelegramTrait;

    protected $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = []): void
    {
        if (($message['text'] ?? '') !== '商户总余额') {
            return;
        }

        if (!$this->checkIsManager($message)) {
            $this->reply($message, '您不是管理员，无权操作此命令');
            return;
        }

        $total = Cache::remember('telegram_merchant_total_balance_amount', now()->addSeconds(10), function () {
            return App::make(GetMerchantTotalBalanceAmountService::class)->excute();
        });

        $this->reply($message, "商户总余额：<code><b>" . floatval($total) . "</b></code>", true);
    }

    protected function reply(array $message, string $text, bool $html = false): void
    {
        $chatId = $message['chat']['id'] ?? 0;
        if (!$chatId) {
            return;
        }

        $payload = ['chat_id' => $chatId, 'text' => $text];
        if ($html) {
            $payload['parse_mode'] = 'html';
        }
        if (!empty($message['message_id'])) {
            $payload['reply_to_message_id'] = $message['message_id'];
        }

        $this->telegram->sendMessage($payload);
    }
}

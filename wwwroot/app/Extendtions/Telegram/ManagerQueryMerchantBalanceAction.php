<?php

namespace App\Extendtions\Telegram;

use App\Models\MerchantInfo;
use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\App;
use App\Services\Telegram\TelegramManagerService;
use App\Services\Telegram\MerchantBalanceTextService;

class ManagerQueryMerchantBalanceAction
{
    use TelegramTrait;

    public $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [])
    {
        $text = trim((string) ($message['text'] ?? ''));
        if (!$this->isBalanceCommand($text)) {
            return;
        }

        $chatId = intval($message['chat']['id'] ?? 0);
        $messageId = intval($message['message_id'] ?? 0);
        if ($chatId <= 0 || !app(TelegramManagerService::class)->isManagerMessage($message)) {
            return;
        }

        $keyword = $this->parseMerchantKeyword($text);
        if ($keyword === '') {
            $this->reply($chatId, '指令格式错误，请使用：商户余额【商户代码/商户编号】', $messageId);
            return;
        }

        $merchant = $this->findMerchant($keyword);
        if (!$merchant) {
            $this->reply($chatId, '商户不存在，查询失败', $messageId);
            return;
        }

        $this->reply($chatId, $this->buildBalanceText($merchant), $messageId, true);
    }

    private function isBalanceCommand(string $text): bool
    {
        return mb_substr($text, 0, 4) === '商家余额' || mb_substr($text, 0, 4) === '商户余额';
    }

    private function parseMerchantKeyword(string $text): string
    {
        return trim(str_replace([' ', '+'], '', preg_replace('/^(商家余额|商户余额)\s*\+?/u', '', $text)));
    }

    private function findMerchant(string $keyword): ?MerchantInfo
    {
        $query = MerchantInfo::query()->select(['merchant_user_id', 'coder', 'name', 'currency_id', 'balance_amount', 'available_balance', 'freeze_amount']);
        if (ctype_digit($keyword)) {
            return $query->where('merchant_user_id', intval($keyword))->first();
        }

        return $query->where('coder', strtoupper($keyword))->first();
    }

    private function buildBalanceText(MerchantInfo $merchant): string
    {
        $lang = $this->telegramLangService()->merchantLang(intval($merchant->merchant_user_id));
        return App::make(MerchantBalanceTextService::class)->excute($merchant, $lang);
    }

    private function reply(int $chatId, string $text, int $messageId = 0, bool $html = false): void
    {
        $data = ['chat_id' => $chatId, 'text' => $text];
        if ($messageId > 0) {
            $data['reply_to_message_id'] = $messageId;
        }
        if ($html) {
            $data['parse_mode'] = 'html';
        }

        $this->telegram->sendMessage($data);
    }
}

<?php

namespace App\Extendtions\Telegram;

use App\Traits\TelegramTrait;
use App\Extendtions\Okx\Quotes;

class QueryOkxAction
{
    use TelegramTrait;

    protected $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = []): void
    {
        $command = strtoupper(trim((string)($message['text'] ?? '')));
        if (!in_array($command, ['OT', 'OW', 'OC', 'OB', 'OA'], true)) {
            return;
        }

        $appName = config('app.name');
        $paymentAppName = payment_app_name();
        $okx = new Quotes();

        if ($command === 'OT') {
            $this->sendQuote($message, '欧易-支付宝', $okx->queryTaobao());
            return;
        }

        if ($command === 'OW') {
            $this->sendQuote($message, '欧易-微信', $okx->queryWeixin());
            return;
        }

        if ($command === 'OC' && $appName === 'sgpay') {
            $this->sendQuote($message, '欧易-银行卡', $okx->queryBank());
            return;
        }

        if ($command === 'OB') {
            $this->sendQuote($message, $paymentAppName === 'sgpay' ? '欧易-全部支付' : '欧易-银行卡', $paymentAppName === 'sgpay' ? $okx->queryAll() : $okx->queryBank());
            return;
        }

        if ($command === 'OA' && $appName !== 'sgpay') {
            $this->sendQuote($message, '欧易-全部支付', $okx->queryAll());
        }
    }

    private function sendQuote(array $message, string $title, $result): void
    {
        $chatId = $message['chat']['id'] ?? 0;
        if (!$chatId) {
            return;
        }

        if (!$result) {
            $this->sendMessage($chatId, '未查询到数据，请联系技术支持！');
            return;
        }

        $text = config('app.name') === 'hhtrade' ? '' : $title . "\n\n";
        foreach ($result as $v) {
            $text .= ($v['price'] ?? '') . '    ' . $this->html($v['name'] ?? '') . "\n";
        }
        if (config('app.name') !== 'hhtrade') {
            $text .= $this->tipCommand();
        }

        $this->sendMessage($chatId, $text);
    }

    private function tipCommand(): string
    {
        if (config('app.name') === 'sgpay') {
            return "\n\n相关查询命令\n欧易-支付宝：ot\n欧易-银行卡：oc\n欧易-微信：ow\n欧易-全部支付：ob\n";
        }

        return "\n\n相关查询命令\n欧易-支付宝：ot\n欧易-银行卡：ob\n欧易-微信：ow\n欧易-全部支付：oa\n";
    }

    private function sendMessage(int $chatId, string $text): void
    {
        $this->telegram->sendMessage(['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'html']);
    }

    private function html($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

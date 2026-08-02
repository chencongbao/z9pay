<?php

namespace App\Extendtions\Telegram;

use App\Jobs\QueryMerchantSuccessInfoJob;
use App\Traits\TelegramTrait;

class QueryMerchantSuccessInfoAction
{
    use TelegramTrait;

    protected $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = []): void
    {
        $text = trim($message['text'] ?? '');
        if (!$this->isSuccessRateCommand($text)) {
            return;
        }

        $chatId = intval($message['chat']['id'] ?? 0);
        if (!$chatId || !$this->getMerchantUserId($message)) {
            return;
        }

        $this->sendSuccessMenu($chatId);
    }

    protected function isSuccessRateCommand(string $text): bool
    {
        return $text === '查询成功率' || preg_match('/^\/success_rate(?:@\w+)?$/i', $text) === 1;
    }

    protected function sendSuccessMenu(int $chatId): void
    {
        if (!$chatId) {
            return;
        }

        $this->telegram->sendMessage(['chat_id' => $chatId, 'text' => '📊 请选择要查询的时间范围：', 'reply_markup' => json_encode($this->buildSuccessMenuKeyboard())]);
    }

    public function callbackQueryTimeReplay($data, $message = []): void
    {
        $callbackMessage = $message['message'] ?? [];
        $chatId = intval($callbackMessage['chat']['id'] ?? 0);
        $messageId = intval($callbackMessage['message_id'] ?? 0);
        $time = (string)($data['time'] ?? '');
        if (!$chatId || !$messageId || !str_starts_with($time, 'sr:')) {
            return;
        }

        $mid = $this->getMerchantUserId($callbackMessage);
        if (!$mid) {
            return;
        }

        $window = substr($time, 3);
        $newText = "⏳ 正在统计 {$this->successWindowLabel($window)} 成功率，请稍候...";
        $this->telegram->editMessageText(['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $newText]);
        QueryMerchantSuccessInfoJob::dispatch($chatId, $window, $mid)->onQueue('query');
    }

    protected function buildSuccessMenuKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '10 分钟', 'callback_data' => json_encode(['type' => 12, 'time' => 'sr:10'])],
                    ['text' => '20 分钟', 'callback_data' => json_encode(['type' => 12, 'time' => 'sr:20'])],
                ],
                [
                    ['text' => '30 分钟', 'callback_data' => json_encode(['type' => 12, 'time' => 'sr:30'])],
                    ['text' => '60 分钟', 'callback_data' => json_encode(['type' => 12, 'time' => 'sr:60'])],
                ],
                [
                    ['text' => '今天（0 点 ~ 现在）', 'callback_data' => json_encode(['type' => 12, 'time' => 'sr:day'])],
                ],
            ],
        ];
    }

    protected function successWindowLabel(string $window): string
    {
        $labelMap = [
            '10'  => '最近 10 分钟',
            '20'  => '最近 20 分钟',
            '30'  => '最近 30 分钟',
            '60'  => '最近 60 分钟',
            'day' => '今天（0 点 ~ 现在）',
        ];

        return $labelMap[$window] ?? '所选时间段';
    }
}

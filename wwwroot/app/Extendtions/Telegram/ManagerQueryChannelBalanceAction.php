<?php

namespace App\Extendtions\Telegram;

use App\Services\Telegram\TelegramChannelBalanceService;
use App\Services\Telegram\TelegramManagerService;

class ManagerQueryChannelBalanceAction
{
    public function __construct(protected $telegram)
    {
    }

    public function excute(array $message = []): void
    {
        if (!app(TelegramManagerService::class)->isPrivateChat($message['chat'] ?? [])) {
            return;
        }

        $service = app(TelegramChannelBalanceService::class);
        $channelId = $service->parseChannelId((string)($message['text'] ?? ''));
        if ($channelId <= 0) {
            $this->reply($message, '指令格式错误，请使用：渠道余额【渠道编号】');
            return;
        }

        $channel = $service->findChannel($channelId);
        if (!$channel) {
            $this->reply($message, '渠道不存在');
            return;
        }

        app(QueryChannelBalanceAction::class, ['telegram' => $this->telegram])->sendBalance($message, $channel);
    }

    private function reply(array $message, string $text): void
    {
        $chatId = intval($message['chat']['id'] ?? 0);
        if ($chatId <= 0) {
            return;
        }

        $payload = ['chat_id' => $chatId, 'text' => $text];
        if (!empty($message['message_id'])) {
            $payload['reply_to_message_id'] = intval($message['message_id']);
        }

        $this->telegram->sendMessage($payload);
    }
}

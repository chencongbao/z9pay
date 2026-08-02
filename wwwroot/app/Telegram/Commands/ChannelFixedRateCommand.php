<?php

namespace App\Telegram\Commands;

use App\Services\Telegram\TelegramManagerService;
use Telegram\Bot\Commands\Command;

class ChannelFixedRateCommand extends Command
{
    protected string $name = 'channel_fixed_rate';

    protected string $description = '私聊机器人修改渠道固定成本费率';

    public function handle()
    {
        $message = $this->getUpdate()->getMessage();
        $chatId = intval($message->chat->id ?? 0);
        if ($chatId <= 0) {
            return null;
        }
        if (!app(TelegramManagerService::class)->isManager(intval($message->from->id ?? 0))) {
            return null;
        }

        $rawText = trim((string) ($message->text ?? ''));
        $commandPrefix = '/' . $this->name;
        if (!str_starts_with(strtolower($rawText), strtolower($commandPrefix))) {
            return null;
        }

        $arguments = trim(substr($rawText, strlen($commandPrefix)));
        if ($arguments === '') {
            return $this->replyWithMessage([
                'text' => '命令格式错误，仅支持：/channel_fixed_rate 1 alipay/3 alipay_uid/5',
            ]);
        }

        $fakeMessage = [
            'chat' => [
                'id' => $chatId,
            ],
            'from' => [
                'id' => intval($message->from->id ?? 0),
            ],
            'message_id' => intval($message->messageId ?? 0),
            'text' => '修改固定渠道费率/' . $arguments,
        ];

        app(ChannelFixedRateAction::class)->excute($fakeMessage);

        return null;
    }
}

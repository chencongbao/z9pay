<?php

namespace App\Telegram\Commands;

use App\Services\Telegram\TelegramManagerService;
use Telegram\Bot\Commands\Command;

class ChannelRateCommand extends Command
{
    protected string $name = 'channel_rate';

    protected string $description = '私聊机器人修改渠道成本费率';

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
                'text' => '命令格式错误，仅支持：/channel_rate 1 alipay/2.1 alipay_uid/2.2',
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
            'text' => '修改渠道费率/' . $arguments,
        ];

        app(ChannelRateAction::class)->excute($fakeMessage);

        return null;
    }
}

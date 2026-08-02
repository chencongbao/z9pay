<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Telegram\TelegramInstanceService;
use App\Services\Telegram\TelegramChannelBalanceService;

class QueryTelegramChannelBalanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 20;

    public function __construct(protected int $channelId, protected int $chatId, protected int $messageId = 0)
    {
    }

    public function handle(): void
    {
        $lockKey = CacheConstPrefixService::TELEGRAM_CHANNEL_BALANCE_QUERY_LOCK . $this->channelId;

        try {
            $service = app(TelegramChannelBalanceService::class);
            $channel = $service->findChannel($this->channelId);
            if (!$channel) {
                $this->sendMessage('渠道不存在或已被删除');
                return;
            }

            $service->refresh($channel);
            $this->replyMessage($service->buildBalanceText($channel));
        } catch (Throwable $e) {
            $this->replyMessage('查询余额失败：' . $e->getMessage());
        } finally {
            Cache::forget($lockKey);
        }
    }

    protected function replyMessage(string $text): void
    {
        if ($this->chatId == 0 || $text === '') {
            return;
        }

        $payload = [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'html',
        ];

        $telegram = app(TelegramInstanceService::class)->excute();
        if ($this->messageId > 0) {
            try {
                $telegram->editMessageText($payload + ['message_id' => $this->messageId]);
                return;
            } catch (Throwable $e) {
                $payload['reply_to_message_id'] = $this->messageId;
            }
        }

        $telegram->sendMessage($payload);
    }
}

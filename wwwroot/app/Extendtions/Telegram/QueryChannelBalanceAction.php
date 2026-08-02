<?php

namespace App\Extendtions\Telegram;

use Throwable;
use App\Models\Channel;
use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\Cache;
use App\Jobs\QueryTelegramChannelBalanceJob;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Telegram\TelegramChannelBalanceService;

class QueryChannelBalanceAction
{
    use TelegramTrait;

    protected $telegram;

    protected array $emptyKeyboard = ['inline_keyboard' => []];

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [], $cid = 0): void
    {
        $this->cancel($message, $cid);
    }

    public function sendBalance($message = [], ?Channel $channel = null, int $callback = 0): void
    {
        if (!$channel) {
            $channelId = (int) $this->getChannelId($message);
            if ($channelId <= 0) {
                return;
            }
            $channel = app(TelegramChannelBalanceService::class)->findChannel($channelId);
        }

        if (!$channel) {
            $this->reply($message, '渠道不存在');
            return;
        }

        $this->queryAndReply($message, $channel, $callback);
    }

    public function editBalanceMessage(array $message = [], ?Channel $channel = null): void
    {
        $chatId = $message['message']['chat']['id'] ?? 0;
        $messageId = $message['message']['message_id'] ?? 0;

        if (!$channel || $chatId == 0 || $messageId <= 0) {
            return;
        }

        try {
            $this->telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => app(TelegramChannelBalanceService::class)->buildBalanceText($channel),
                'parse_mode' => 'html',
            ]);

            $this->telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => json_encode(app(TelegramChannelBalanceService::class)->buildKeyboard($channel->id)),
            ]);
        } catch (Throwable $e) {
            $this->sendChatMessage($chatId, '渠道余额展示失败：' . $e->getMessage());
        }
    }

    public function cancel($message = [], $cid = 0): void
    {
        $chatId = $message['message']['chat']['id'] ?? 0;
        $messageId = $message['message']['message_id'] ?? 0;

        if ($chatId == 0 || $messageId == 0 || (int) $cid <= 0) {
            if ($chatId != 0) {
                $this->sendChatMessage($chatId, '参数错误');
            }
            return;
        }

        $channel = app(TelegramChannelBalanceService::class)->findChannel((int) $cid);
        if (!$channel) {
            $this->clearMessageKeyboard($chatId, $messageId);
            $this->sendChatMessage($chatId, '渠道不存在');
            return;
        }

        $this->queryAndReply($message, $channel, 1, true);
    }

    protected function queryAndReply(array $message, Channel $channel, int $callback = 0, bool $editMessage = false): void
    {
        $chatId = $editMessage ? ($message['message']['chat']['id'] ?? 0) : ($message['chat']['id'] ?? 0);
        $messageId = $editMessage ? ($message['message']['message_id'] ?? 0) : 0;
        if ($chatId == 0) {
            return;
        }

        $lockKey = CacheConstPrefixService::TELEGRAM_CHANNEL_BALANCE_QUERY_LOCK . $channel->id;
        if (!Cache::add($lockKey, 1, now()->addSeconds(30))) {
            $this->sendChatMessage($chatId, '渠道余额正在查询中，请稍候...');
            return;
        }

        // 先提示正在远程查询，队列完成后再替换为最新余额，避免 webhook 被渠道接口阻塞。
        if ($editMessage && $messageId > 0) {
            try {
                $this->telegram->editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => '正在远程查询渠道余额，请稍候...',
                    'parse_mode' => 'html',
                    'reply_markup' => json_encode($this->emptyKeyboard),
                ]);
            } catch (Throwable $e) {
                $this->sendChatMessage($chatId, '正在远程查询渠道余额，请稍候...');
            }
        } else {
            $response = $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '正在远程查询渠道余额，请稍候...',
                'parse_mode' => 'html',
            ]);
            $messageId = $this->responseMessageId($response);
        }

        dispatch(new QueryTelegramChannelBalanceJob((int) $channel->id, (int) $chatId, (int) $messageId))->onQueue('query');
    }

    protected function responseMessageId($response): int
    {
        if (is_array($response)) {
            return intval($response['message_id'] ?? 0);
        }

        if (is_object($response) && method_exists($response, 'getMessageId')) {
            return intval($response->getMessageId());
        }

        if (is_object($response) && method_exists($response, 'toArray')) {
            $data = $response->toArray();
            return intval($data['message_id'] ?? 0);
        }

        return 0;
    }

    protected function reply(array $message, string $text): void
    {
        $payload = ['chat_id' => $message['chat']['id'] ?? 0, 'text' => $text];

        if (isset($message['message_id'])) {
            $payload['reply_to_message_id'] = $message['message_id'];
        }

        if (($payload['chat_id'] ?? 0) != 0) {
            $this->telegram->sendMessage($payload);
        }
    }

    protected function sendMessage(array $message, string $text, array $keyboard = [], int $callback = 0): void
    {
        $chatId = $message['chat']['id'] ?? 0;
        if (!$chatId) {
            return;
        }

        $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'html'];
        if (!empty($keyboard)) {
            $payload['reply_markup'] = json_encode($keyboard);
        }
        if ($callback !== 1 && !empty($message['message_id'])) {
            $payload['reply_to_message_id'] = $message['message_id'];
        }

        $this->telegram->sendMessage($payload);
    }

    protected function sendChatMessage(int $chatId, string $text): void
    {
        if ($chatId === 0) {
            return;
        }

        $this->telegram->sendMessage(['chat_id' => $chatId, 'text' => $text]);
    }

    protected function clearMessageKeyboard(int $chatId, int $messageId): void
    {
        if ($chatId === 0 || $messageId === 0) {
            return;
        }

        $this->telegram->editMessageReplyMarkup(['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => json_encode($this->emptyKeyboard)]);
    }

}

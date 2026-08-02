<?php

namespace App\Extendtions\Telegram;

use App\Models\Channel;
use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class BingChanelAction
{
    use TelegramTrait;

    public $telegram;

    public $keyboard = [
        'inline_keyboard' => []
    ];

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [], $group_type = 0)
    {
        $text = trim((string) ($message['text'] ?? ''));
        $chatId = $message['chat']['id'] ?? 0;
        $messageId = $message['message_id'] ?? 0;

        if (mb_strpos($text, '渠道绑定') === 0 && !preg_match('/^渠道绑定\s*\+?(\d+)$/u', $text)) {
            $this->reply($chatId, '指令格式错误，请使用：渠道绑定 123', $messageId);
            return;
        }

        if (!preg_match('/^渠道绑定\s*\+?(\d+)$/u', $text, $matches)) {
            return;
        }

        if (!in_array((int) $group_type, [0, 3], true)) {
            $this->reply($chatId, '已绑定其他用户类型，无法重复绑定渠道', $messageId);
            return;
        }
        if (!$this->checkIsManager($message)) {
            $this->reply($chatId, '您不是管理员，无权操作此命令', $messageId);
            return;
        }

        $channelId = intval($matches[1] ?? 0);
        $lockKey = "telegram_bind_channel:{$chatId}:{$channelId}";
        if (!Cache::add($lockKey, 1, now()->addSeconds(10))) {
            $this->reply($chatId, '渠道绑定处理中，请勿重复操作', $messageId);
            return;
        }

        try {
            $this->bindChannel($channelId, $chatId, $messageId);
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function bindChannel(int $channelId, int $chatId, int $messageId): void
    {
        $channel = Channel::whereKey($channelId)->first(['id', 'telegram_user_id']);
        if (!$channel) {
            $this->reply($chatId, '渠道不存在，请检查渠道ID是否正确', $messageId);
            return;
        }
        if ($channel->telegram_user_id != 0 && $channel->telegram_user_id != $chatId) {
            $this->reply($chatId, '该渠道已绑定其他群或会话，请先解绑后再操作', $messageId);
            return;
        }
        if ($channel->telegram_user_id == $chatId) {
            $this->reply($chatId, '该渠道已绑定当前群，无需重复绑定', $messageId);
            return;
        }

        // 带未绑定条件更新，避免多个群同时绑定同一个渠道。
        $updated = Channel::whereKey($channelId)
            ->where(function ($query) {
                $query->where('telegram_user_id', 0)->orWhereNull('telegram_user_id');
            })
            ->update(['telegram_user_id' => $chatId]);
        if (!$updated) {
            $this->reply($chatId, '该渠道已绑定其他群或会话，请先解绑后再操作', $messageId);
            return;
        }

        $this->clearGroupTypeCache($chatId);
        $this->reply($chatId, '渠道绑定成功，当前群可继续绑定其他渠道', $messageId, true);
    }

    private function clearGroupTypeCache(int $chatId): void
    {
        $key = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $chatId;
        foreach (['', '_id', '_ids', '_name', '_type'] as $suffix) {
            Cache::forget($key . $suffix);
        }
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

<?php

namespace App\Extendtions\Telegram;

use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class ExceptionNoticeMuteClickService
{
    public $telegram;

    public $keyboard = [
        'inline_keyboard' => []
    ];

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function action(array $data = [], array $message = []): void
    {
        $callbackKey = (string) ($data['key'] ?? $data['k'] ?? '');
        $callbackTime = intval($data['time'] ?? $data['m'] ?? 0);
        $chatId = intval($message['message']['chat']['id'] ?? 0);
        $messageId = intval($message['message']['message_id'] ?? 0);

        if ($callbackKey === '' || $callbackTime <= 0) {
            $this->reply($chatId, '静默参数错误，请稍后重试', $messageId);
            return;
        }

        if (!Cache::add($this->lockKey($callbackKey), 1, now()->addSeconds(5))) {
            return;
        }

        Cache::put($this->muteKey($callbackKey), 1, $callbackTime);
        $this->clearKeyboard($chatId, $messageId);
        $this->reply($chatId, '此类型消息将静默' . $this->formatMuteMinutes($callbackTime) . '分钟', $messageId);
    }

    private function muteKey(string $callbackKey): string
    {
        return CacheConstPrefixService::SEND_CHANNEL_EXCEPTION_NOTICE . $callbackKey;
    }

    private function lockKey(string $callbackKey): string
    {
        return $this->muteKey($callbackKey) . ':mute_lock';
    }

    private function clearKeyboard(int $chatId, int $messageId): void
    {
        $this->telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode($this->keyboard),
        ]);
    }

    private function reply(int $chatId, string $text, int $messageId = 0): void
    {
        $data = ['chat_id' => $chatId, 'text' => $text];
        if ($messageId > 0) {
            $data['reply_to_message_id'] = $messageId;
        }

        $this->telegram->sendMessage($data);
    }

    private function formatMuteMinutes(int $seconds): string
    {
        $minutes = $seconds / 60;

        return fmod($minutes, 1.0) === 0.0 ? (string) intval($minutes) : (string) round($minutes, 2);
    }
}

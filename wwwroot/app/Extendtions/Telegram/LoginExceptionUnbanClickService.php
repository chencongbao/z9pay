<?php

namespace App\Extendtions\Telegram;

use App\Models\IpBlacklist;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Enums\LoginExceptionTypeEnum;

class LoginExceptionUnbanClickService
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
        $chatId = intval($message['message']['chat']['id'] ?? 0);
        $messageId = intval($message['message']['message_id'] ?? 0);

        if ($callbackKey === '') {
            return;
        }

        if (!Cache::add($this->lockKey($callbackKey), 1, now()->addSeconds(5))) {
            return;
        }

        try {
            $banInfo = (array) Cache::get($this->banInfoKey($callbackKey), []);
            if (empty($banInfo['ip'])) {
                $this->reply($chatId, '解封信息已失效，请等待新的报警消息', $messageId, true);
                return;
            }

            $type = (string) ($banInfo['type'] ?? 'all');
            $this->unbanIp($banInfo, $type);
            $this->clearKeyboard($chatId, $messageId);
            $this->reply($chatId, '已解封IP：' . $banInfo['ip'] . '，类型：' . LoginExceptionTypeEnum::label($type), $messageId);
        } finally {
            Cache::forget($this->lockKey($callbackKey));
        }
    }

    private function unbanIp(array $banInfo, string $type): void
    {
        IpBlacklist::query()
            ->where('ip', $banInfo['ip'])
            ->where('type', $type)
            ->update([
                'status' => 0,
                'remark' => 'Telegram手动解封',
            ]);
    }

    private function banInfoKey(string $callbackKey): string
    {
        return CacheConstPrefixService::TELEGRAM_LOGIN_EXCEPTION_BAN_INFO . $callbackKey;
    }

    private function lockKey(string $callbackKey): string
    {
        return $this->banInfoKey($callbackKey) . ':unban_lock';
    }

    private function clearKeyboard(int $chatId, int $messageId): void
    {
        $this->telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode($this->keyboard),
        ]);
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

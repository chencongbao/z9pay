<?php

namespace App\Extendtions\Telegram;

use App\Models\IpBlacklist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Enums\LoginExceptionTypeEnum;

class LoginExceptionBanClickService
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

        if ($callbackKey === '') {
            return;
        }

        if (!Cache::add($this->lockKey($callbackKey), 1, now()->addSeconds(5))) {
            return;
        }

        try {
            $banInfo = (array) Cache::get($this->banInfoKey($callbackKey), []);
            if (empty($banInfo['ip'])) {
                $this->reply($chatId, '封禁信息已失效，请等待新的报警消息', $messageId, true);
                return;
            }

            $type = (string) ($banInfo['type'] ?? 'all');
            $expiresAt = $callbackTime > 0 ? Carbon::now()->addSeconds($callbackTime) : null;
            $this->banIp($banInfo, $type, $expiresAt);
            $this->clearKeyboard($chatId, $messageId);
            $this->reply($chatId, $this->successText($banInfo, $type, $expiresAt), $messageId, false, $this->unbanKeyboard($callbackKey));
        } finally {
            Cache::forget($this->lockKey($callbackKey));
        }
    }

    private function banIp(array $banInfo, string $type, ?Carbon $expiresAt): void
    {
        IpBlacklist::query()->updateOrCreate(
            [
                'ip' => $banInfo['ip'],
                'type' => $type,
            ],
            [
                'status' => 1,
                'reason' => 'Telegram手动封禁登录IP',
                'remark' => '系统端:' . ($banInfo['usertype'] ?? '') . ' 用户名:' . ($banInfo['username'] ?? ''),
                'locked_at' => Carbon::now(),
                'expires_at' => $expiresAt,
            ]
        );
    }

    private function banInfoKey(string $callbackKey): string
    {
        return CacheConstPrefixService::TELEGRAM_LOGIN_EXCEPTION_BAN_INFO . $callbackKey;
    }

    private function lockKey(string $callbackKey): string
    {
        return $this->banInfoKey($callbackKey) . ':ban_lock';
    }

    private function clearKeyboard(int $chatId, int $messageId): void
    {
        $this->telegram->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode($this->keyboard),
        ]);
    }

    private function reply(int $chatId, string $text, int $messageId = 0, bool $html = false, ?array $keyboard = null): void
    {
        $data = ['chat_id' => $chatId, 'text' => $text];
        if ($messageId > 0) {
            $data['reply_to_message_id'] = $messageId;
        }
        if ($html) {
            $data['parse_mode'] = 'html';
        }
        if ($keyboard !== null) {
            $data['reply_markup'] = json_encode($keyboard);
        }

        $this->telegram->sendMessage($data);
    }

    private function unbanKeyboard(string $callbackKey): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '✅解封', 'callback_data' => json_encode(['t' => 16, 'k' => $callbackKey])]
                ]
            ],
        ];
    }

    private function successText(array $banInfo, string $type, ?Carbon $expiresAt): string
    {
        return '已封禁IP：' . $banInfo['ip'] . '，类型：' . LoginExceptionTypeEnum::label($type) . '，到期时间：' . ($expiresAt ? $expiresAt->format('Y-m-d H:i:s') : '永久');
    }
}

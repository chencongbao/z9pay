<?php

namespace App\Extendtions\Telegram;

use App\Models\User;
use Illuminate\Support\Str;
use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;

class BindUser
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
        if (!str_starts_with($text, '申请绑定')) {
            return;
        }

        $chatId = $message['chat']['id'] ?? 0;
        $fromId = $message['from']['id'] ?? 0;
        $messageId = $message['message_id'] ?? 0;
        if (in_array(intval($group_type), [1, 3], true)) {
            $this->reply($chatId, '已绑定其他用户类型，无法重复绑定', $messageId);
            return;
        }

        $boundUser = $this->findBoundUser($chatId, $fromId);
        if ($boundUser) {
            $this->reply($chatId, '当前飞机账号已绑定金主【' . $boundUser->bname . '】，如需绑定其他金主，请先解绑当前金主', $messageId);
            return;
        }

        $field = $this->parseBindField($text);
        if ($field === '') {
            $this->reply($chatId, '指令格式错误，请使用：申请绑定+金主账号或金主编号', $messageId);
            return;
        }

        $user = $this->findUser($field);
        if (!$user) {
            $this->reply($chatId, '金主不存在，绑定失败', $messageId, true);
            return;
        }
        if ($user->telegram_group_id != 0) {
            $this->reply($chatId, '金主【' . $user->bname . '】，已绑定，绑定失败', $messageId);
            return;
        }

        $key = 'bu:' . $fromId . ':' . Str::random(8);
        Cache::put($key, ['from_id' => $fromId, 'field' => $field], now()->addMinutes(10));
        $this->telegram->sendMessage(['chat_id' => $chatId, 'text' => "请管理员授权", 'reply_markup' => json_encode($this->bindKeyboard($key)), 'parse_mode' => 'html', 'reply_to_message_id' => $messageId]);
    }

    public function action($data, $message)
    {
        $key = $data['key'] ?? $data['k'] ?? '';
        $action = $data['action'] ?? $data['a'] ?? '';
        $chatId = $message['chat']['id'] ?? 0;
        $messageId = $message['message_id'] ?? 0;
        if (!$this->lockCallbackAction($chatId, $messageId)) {
            return;
        }

        $sdata = Cache::get($key);
        if (!$sdata) {
            $this->editText($chatId, $messageId, '数据错误，请重新申请');
            return;
        }

        if (in_array($action, ['confirm', 'c'], true)) {
            $this->confirmBind($chatId, $messageId, $sdata);
            Cache::forget($key);
            return;
        }
        if (in_array($action, ['cancel', 'x'], true)) {
            $this->clearReplyMarkup($chatId, $messageId);
            $this->editText($chatId, $messageId, '对不起，你没有被允许绑定金主');
            Cache::forget($key);
        }
    }

    private function confirmBind(int $chatId, int $messageId, array $data): void
    {
        $fromId = intval($data['from_id'] ?? 0);
        $field = trim((string) ($data['field'] ?? ''));
        $boundUser = $this->findBoundUser($chatId, $fromId);
        if ($boundUser) {
            $this->clearReplyMarkup($chatId, $messageId);
            $this->editText($chatId, $messageId, '当前飞机账号已绑定金主【' . $boundUser->bname . '】，如需绑定其他金主，请先解绑当前金主');
            return;
        }

        $user = $this->findUser($field);
        if (!$user) {
            $this->clearReplyMarkup($chatId, $messageId);
            $this->editText($chatId, $messageId, '金主不存在，绑定失败', true);
            return;
        }
        if ($user->telegram_group_id != 0) {
            $this->clearReplyMarkup($chatId, $messageId);
            $this->editText($chatId, $messageId, '金主【' . $user->bname . '】，已绑定，绑定失败');
            return;
        }

        // 确认时再带未绑定条件更新，避免多个管理员重复确认造成覆盖。
        $updated = User::where('id', $user->id)
            ->where(function ($query) {
                $query->where('telegram_group_id', 0)->orWhereNull('telegram_group_id');
            })
            ->update(['telegram_group_id' => $chatId, 'telegram_user_id' => $fromId]);
        if (!$updated) {
            $this->clearReplyMarkup($chatId, $messageId);
            $this->editText($chatId, $messageId, '金主【' . $user->bname . '】，已绑定，绑定失败');
            return;
        }

        Cache::forever(CacheConstPrefixService::TELEGRAM_GROUP_AND_USER_ID . $fromId, $user->id);
        $this->clearGroupTypeCache($chatId);
        $this->reply($chatId, '金主绑定成功：【<b>' . $user->bname . '</b>】', $messageId, true);
    }

    private function parseBindField(string $text): string
    {
        return trim((string) preg_replace('/^申请绑定[\s　]*[+＋]?[\s　]*/u', '', $text));
    }

    private function findBoundUser(int $chatId, int $fromId)
    {
        return User::where('telegram_group_id', $chatId)->where('telegram_user_id', $fromId)->where('is_agent', 0)->first(['id', 'name', 'username']);
    }

    private function findUser(string $field)
    {
        return User::where('is_agent', 0)->where(function ($query) use ($field) {
            $query->where('id', $field)->orWhere('username', $field);
        })->first(['id', 'name', 'username', 'telegram_group_id']);
    }

    private function bindKeyboard(string $key): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '确认绑定', 'callback_data' => json_encode(['t' => 4, 'a' => 'c', 'k' => $key])],
                    ['text' => '取消绑定', 'callback_data' => json_encode(['t' => 4, 'a' => 'x', 'k' => $key])],
                ]
            ],
        ];
    }

    private function clearGroupTypeCache(int $chatId): void
    {
        $groupKey = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $chatId;
        foreach (['', '_id', '_ids', '_name', '_type'] as $suffix) {
            Cache::forget($groupKey . $suffix);
        }
    }

    private function lockCallbackAction(int $chatId, int $messageId): bool
    {
        return Cache::add("telegram_bind_user_action:{$chatId}:{$messageId}", 1, now()->addSeconds(5));
    }

    private function clearReplyMarkup(int $chatId, int $messageId): void
    {
        $this->telegram->editMessageReplyMarkup(['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => json_encode($this->keyboard)]);
    }

    private function editText(int $chatId, int $messageId, string $text, bool $html = false): void
    {
        $data = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text];
        if ($html) {
            $data['parse_mode'] = 'html';
        }

        $this->telegram->editMessageText($data);
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

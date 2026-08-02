<?php

namespace App\Extendtions\Telegram;

use App\Models\User;
use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Telegram\TelegramManagerService;

class ClearTelegramCacheAction
{
    use TelegramTrait;

    public const COMMANDS = ['清除飞机缓存', '清空飞机缓存', '清除TG缓存'];

    public $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = []): void
    {
        $text = trim((string) ($message['text'] ?? ''));
        if (!$this->isClearCommand($text)) {
            return;
        }

        $chatId = intval($message['chat']['id'] ?? 0);
        $messageId = intval($message['message_id'] ?? 0);
        if (!app(TelegramManagerService::class)->isDeveloperMessage($message)) {
            $this->reply($chatId, '无权限执行该命令', $messageId);
            return;
        }

        $this->clearGroupTypeCache($chatId);

        $userId = $this->parseUserId($text);
        if ($userId > 0) {
            if (intval($this->getGroup($message)) !== 2) {
                $this->reply($chatId, '清除指定金主飞机缓存只能在金主群使用', $messageId, true);
                return;
            }

            $user = User::query()->whereKey($userId)->first(['id', 'username', 'name', 'telegram_group_id', 'telegram_user_id']);
            if (!$user) {
                $this->reply($chatId, '已清除当前会话飞机缓存，指定金主不存在', $messageId, true);
                return;
            }

            $this->clearUserTelegramCache((int) $user->telegram_group_id, (int) $user->telegram_user_id);
            $this->reply($chatId, '已清除当前会话和金主【' . $user->bname . '】飞机缓存', $messageId, true);
            return;
        }

        $this->reply($chatId, '已清除当前会话飞机缓存', $messageId, true);
    }

    private function isClearCommand(string $text): bool
    {
        foreach (self::COMMANDS as $command) {
            if ($text === $command || preg_match('/^' . preg_quote($command, '/') . '[\s　]*[+＋][\s　]*\d+$/u', $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private function parseUserId(string $text): int
    {
        if (preg_match('/[+＋][\s　]*(\d+)$/u', $text, $matches) !== 1) {
            return 0;
        }

        return intval($matches[1] ?? 0);
    }

    private function clearUserTelegramCache(int $chatId, int $fromId): void
    {
        if ($chatId !== 0) {
            $this->clearGroupTypeCache($chatId);
        }

        if ($fromId > 0) {
            $this->clearGroupTypeCache($fromId);
            Cache::forget(CacheConstPrefixService::TELEGRAM_GROUP_AND_USER_ID . $fromId);
        }

        if ($chatId !== 0 && $fromId > 0) {
            Cache::forget(CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $fromId . '_missing_' . $chatId);
        }
    }

    private function clearGroupTypeCache(int $chatId): void
    {
        $key = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $chatId;
        foreach (['', '_id', '_ids', '_name', '_type'] as $suffix) {
            Cache::forget($key . $suffix);
        }
        Cache::forget(CacheConstPrefixService::TELEGRAM_GROUP_AND_MERCHAND_USER_ID . $chatId);
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

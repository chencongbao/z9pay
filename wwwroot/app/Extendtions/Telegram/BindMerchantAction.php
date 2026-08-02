<?php

namespace App\Extendtions\Telegram;

use App\Models\MerchantInfo;
use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class BindMerchantAction
{
    use TelegramTrait;

    public $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [], $group_type = 0)
    {
        $text = trim((string) ($message['text'] ?? ''));
        $chatId = $message['chat']['id'] ?? 0;
        $messageId = $message['message_id'] ?? 0;

        if (!preg_match('/^(bd\s*\+?.+|绑定\s*\+?.+)$/iu', $text)) {
            return;
        }

        if (mb_strpos($text, '渠道绑定') === 0) {
            return;
        }

        if ($text === '绑定' || strtolower($text) === 'bd') {
            $this->reply($chatId, '指令格式错误，请使用：bd+商户代码 或 绑定+商户代码', $messageId);
            return;
        }

        if ($group_type != 0) {
            $this->reply($chatId, '已绑定其他用户类型，无法重复绑定', $messageId);
            return;
        }
        if (!$this->checkIsManager($message)) {
            $this->reply($chatId, '您不是管理员，无权操作此命令', $messageId);
            return;
        }

        $lockKey = "telegram_bind_merchant:{$chatId}";
        if (!Cache::add($lockKey, 1, now()->addSeconds(10))) {
            $this->reply($chatId, '绑定处理中，请勿重复操作', $messageId);
            return;
        }

        try {
            $this->bindMerchant($chatId, $messageId, $text);
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function bindMerchant(int $chatId, int $messageId, string $text): void
    {
        $boundMerchant = MerchantInfo::where('telegram_group_id', $chatId)->first(['merchant_user_id', 'coder', 'name']);
        if ($boundMerchant) {
            $this->reply($chatId, '当前群组已绑定商户【' . $boundMerchant->coder . '】，请勿重复绑定', $messageId);
            return;
        }

        $code = trim((string) preg_replace('/^(bd|绑定)\s*\+?/iu', '', $text));
        if ($code === '') {
            $this->reply($chatId, '指令格式错误，请使用：bd+商户代码 或 绑定+商户代码', $messageId);
            return;
        }

        $merchant = MerchantInfo::where('coder', strtoupper($code))->first(['merchant_user_id', 'telegram_group_id', 'coder']);
        if (!$merchant) {
            $this->reply($chatId, '商户代码不存在，绑定失败', $messageId, true);
            return;
        }
        if ($merchant->telegram_group_id != 0) {
            $this->reply($chatId, '当前商户【' . $merchant->coder . '】，已绑定其他群组，绑定失败', $messageId);
            return;
        }

        // 先落库再刷新缓存，避免缓存显示已绑定但数据库更新失败。
        $updated = MerchantInfo::where('merchant_user_id', $merchant->merchant_user_id)
            ->where(function ($query) {
                $query->where('telegram_group_id', 0)->orWhereNull('telegram_group_id');
            })
            ->update(['telegram_group_id' => $chatId]);
        if (!$updated) {
            $this->reply($chatId, '当前商户【' . $merchant->coder . '】，已绑定其他群组，绑定失败', $messageId);
            return;
        }

        Cache::forever(CacheConstPrefixService::TELEGRAM_GROUP_AND_MERCHAND_USER_ID . $chatId, $merchant->merchant_user_id);
        App::make(CacheMerchantBaseInfoService::class)->excute($merchant->merchant_user_id, true);
        $this->clearGroupTypeCache($chatId);
        $this->reply($chatId, '商户绑定成功：【<b>' . strtoupper($code) . '</b>】', $messageId, true);
    }

    private function clearGroupTypeCache(int $chatId): void
    {
        $groupKey = CacheConstPrefixService::TELEGRAM_GROUP_TYPE . $chatId;
        foreach (['_id', '_ids', '_name', '_type'] as $suffix) {
            Cache::forget($groupKey . $suffix);
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

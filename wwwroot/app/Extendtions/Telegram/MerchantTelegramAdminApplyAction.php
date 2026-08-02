<?php

namespace App\Extendtions\Telegram;

use App\Models\MerchantTelegramAdmin;
use App\Traits\TelegramTrait;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Services\Telegram\TelegramOperatorService;

class MerchantTelegramAdminApplyAction
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

    public function excute(array $message = [], int $groupType = 0): void
    {
        $chatId = intval($message['chat']['id'] ?? 0);
        $messageId = intval($message['message_id'] ?? 0);
        $fromId = intval($message['from']['id'] ?? 0);

        if ($chatId >= 0) {
            $this->reply($chatId, '该命令只能在商户群里使用', $messageId);
            return;
        }
        if ($groupType !== 1) {
            $this->reply($chatId, '当前群未绑定商户，无法申请商户群管理员', $messageId);
            return;
        }
        if ($fromId <= 0) {
            $this->reply($chatId, '未获取到申请人Telegram用户ID，请重新发送命令', $messageId);
            return;
        }

        $mid = intval($this->getMerchantUserId($message));
        if ($mid <= 0) {
            $this->reply($chatId, '当前群未获取到商户信息，请先绑定商户', $messageId);
            return;
        }

        $lockKey = "merchant_telegram_admin_apply:{$mid}:{$fromId}";
        if (!Cache::add($lockKey, 1, now()->addSeconds(10))) {
            $this->reply($chatId, '申请处理中，请勿重复提交', $messageId);
            return;
        }

        try {
            $this->apply($message, $mid, $chatId, $fromId, $messageId);
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function apply(array $message, int $mid, int $chatId, int $fromId, int $messageId): void
    {
        $telegramAdmin = MerchantTelegramAdmin::query()
            ->where('mid', $mid)
            ->where('telegram_group_id', $chatId)
            ->where('telegram_user_id', $fromId)
            ->first();

        if ($telegramAdmin) {
            $this->reply($chatId, '您已经是当前商户群管理员，无需重复申请', $messageId);
            return;
        }

        $data = [
            'mid' => $mid,
            'telegram_group_id' => $chatId,
            'telegram_user_id' => $fromId,
            'telegram_username' => (string)($message['from']['username'] ?? ''),
            'telegram_name' => $this->operatorName($message),
        ];
        $key = 'mta:' . Str::random(16);
        Cache::put($key, $data, now()->addMinutes(10));

        $this->sendConfirmMessage($data, $key, $message, '已提交商户群管理员申请，请管理员确认');
    }

    public function confirm(array $data, array $callbackMessage): void
    {
        $this->handleCallback($data, $callbackMessage, true);
    }

    public function reject(array $data, array $callbackMessage): void
    {
        $this->handleCallback($data, $callbackMessage, false);
    }

    private function handleCallback(array $data, array $callbackMessage, bool $confirmed): void
    {
        $chatId = intval($callbackMessage['message']['chat']['id'] ?? 0);
        $messageId = intval($callbackMessage['message']['message_id'] ?? 0);
        if (!$this->lockCallbackAction($chatId, $messageId)) {
            return;
        }

        $key = (string)($data['k'] ?? '');
        $applyData = Cache::get($key);
        if (!is_array($applyData)) {
            $this->editText($chatId, $messageId, '申请已失效，请重新申请');
            return;
        }

        Cache::forget($key);
        if (!$confirmed) {
            $this->editText($chatId, $messageId, $this->callbackText($applyData, $callbackMessage, '已拒绝申请'), true);
            return;
        }

        $operator = app(TelegramOperatorService::class)->context($callbackMessage);
        $telegramAdmin = MerchantTelegramAdmin::query()
            ->where('mid', intval($applyData['mid'] ?? 0))
            ->where('telegram_group_id', intval($applyData['telegram_group_id'] ?? 0))
            ->where('telegram_user_id', intval($applyData['telegram_user_id'] ?? 0))
            ->first();
        $saveData = array_merge($applyData, [
            'reviewed_by' => $operator['admin_id'],
            'reviewed_telegram_user_id' => $operator['telegram_user_id'],
            'reviewed_telegram_name' => $operator['telegram_name'],
        ]);
        if ($telegramAdmin) {
            $telegramAdmin->forceFill($saveData)->save();
        } else {
            MerchantTelegramAdmin::query()->create($saveData);
        }

        $this->editText($chatId, $messageId, $this->callbackText($applyData, $callbackMessage, '已确认授权'), true);
    }

    private function operatorName(array $message): string
    {
        $name = trim((string)($message['from']['first_name'] ?? '') . ' ' . (string)($message['from']['last_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return (string)($message['from']['username'] ?? ($message['from']['id'] ?? 'Telegram用户'));
    }

    private function sendConfirmMessage(array $data, string $key, array $message, string $tips): void
    {
        $chatId = intval($message['chat']['id'] ?? 0);
        $messageId = intval($message['message_id'] ?? 0);
        $payload = [
            'chat_id' => $chatId,
            'text' => $this->applyText($data, $tips),
            'reply_markup' => json_encode($this->confirmKeyboard($key)),
            'parse_mode' => 'html',
        ];
        if ($messageId > 0) {
            $payload['reply_to_message_id'] = $messageId;
        }

        $this->telegram->sendMessage($payload);
    }

    private function applyText(array $data, string $tips): string
    {
        return "商户群管理员申请\n"
            . "商户编号：<b>" . intval($data['mid'] ?? 0) . "</b>\n"
            . "申请人：<b>{$this->escape((string)($data['telegram_name'] ?? ''))}</b>\n"
            . "Telegram ID：<code>" . intval($data['telegram_user_id'] ?? 0) . "</code>\n"
            . "状态：{$tips}";
    }

    private function callbackText(array $data, array $callbackMessage, string $text): string
    {
        return "商户群管理员申请\n"
            . "商户编号：<b>" . intval($data['mid'] ?? 0) . "</b>\n"
            . "申请人：<b>{$this->escape((string)($data['telegram_name'] ?? ''))}</b>\n"
            . "Telegram ID：<code>" . intval($data['telegram_user_id'] ?? 0) . "</code>\n"
            . "操作结果：<b>{$text}</b>\n"
            . "确认人：<b>{$this->escape(app(TelegramOperatorService::class)->telegramName($callbackMessage))}</b>";
    }

    private function confirmKeyboard(string $key): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '确认授权', 'callback_data' => json_encode(['t' => 25, 'k' => $key])],
                    ['text' => '拒绝申请', 'callback_data' => json_encode(['t' => 26, 'k' => $key])],
                ]
            ],
        ];
    }

    private function lockCallbackAction(int $chatId, int $messageId): bool
    {
        return Cache::add("merchant_telegram_admin_callback:{$chatId}:{$messageId}", 1, now()->addSeconds(5));
    }

    private function editText(int $chatId, int $messageId, string $text, bool $html = false): void
    {
        $payload = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text, 'reply_markup' => json_encode($this->keyboard)];
        if ($html) {
            $payload['parse_mode'] = 'html';
        }

        $this->telegram->editMessageText($payload);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function reply(int $chatId, string $text, int $messageId = 0): void
    {
        $payload = ['chat_id' => $chatId, 'text' => $text];
        if ($messageId > 0) {
            $payload['reply_to_message_id'] = $messageId;
        }

        $this->telegram->sendMessage($payload);
    }
}

<?php

namespace App\Extendtions\Telegram;

use App\Models\TransferOrder;
use App\Models\MerchantTelegramAdmin;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use App\Jobs\SendTransferOrderTelegramConfirmPayJob;
use App\Jobs\SendTransferOrderTelegramConfirmCancelJob;

class TransferOrderConfirmClickService
{
    protected $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function confirm($message = [], $order_id = 0): void
    {
        $this->handleAction($message, intval($order_id), '已确认发起代付', SendTransferOrderTelegramConfirmPayJob::class);
    }

    public function cancel($message = [], $order_id = 0): void
    {
        $this->handleAction($message, intval($order_id), '已确认取消代付', SendTransferOrderTelegramConfirmCancelJob::class);
    }

    private function handleAction(array $message, int $orderId, string $actionText, string $jobClass): void
    {
        $chatId = $this->chatId($message);
        $messageId = $this->messageId($message);
        if ($orderId <= 0 || !$chatId || !$messageId) {
            return;
        }
        if (!$this->canConfirmTransferOrder($orderId, $chatId, $this->telegramUserId($message))) {
            $this->answerCallbackQuery($message, '只有已审核的商户群管理员可以操作');
            return;
        }

        $key = CacheConstPrefixService::TRANSFER_ORDER_CONFIRM_ACTION . $orderId;
        if (!Cache::add($key, 1, now()->addMinutes(30))) {
            $this->answerCallbackQuery($message, '请勿重复操作');
            $this->reply($chatId, $messageId, '请勿重复操作');
            return;
        }

        $this->answerCallbackQuery($message);
        $this->telegram->editMessageReplyMarkup(['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => json_encode($this->buildHandledKeyboard($message, $actionText, $chatId))]);
        $jobClass::dispatch($orderId, $message)->onQueue('notice');
    }

    private function canConfirmTransferOrder(int $orderId, int $chatId, int $telegramUserId): bool
    {
        if ($telegramUserId <= 0) {
            return false;
        }

        $order = TransferOrder::query()->whereKey($orderId)->first(['id', 'mid']);
        if (!$order) {
            return false;
        }

        return MerchantTelegramAdmin::query()
            ->where('mid', (int)$order->mid)
            ->where('telegram_group_id', $chatId)
            ->where('telegram_user_id', $telegramUserId)
            ->exists();
    }

    private function buildHandledKeyboard(array $message, string $actionText, int $chatId): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '【' . $this->operatorName($message) . '】' . $actionText, 'callback_data' => $chatId],
                ],
            ],
        ];
    }

    private function operatorName(array $message): string
    {
        $name = trim((string)($message['from']['first_name'] ?? '') . (string)($message['from']['last_name'] ?? ''));

        return $name !== '' ? $name : 'Telegram用户';
    }

    private function reply(int $chatId, int $messageId, string $text): void
    {
        $this->telegram->sendMessage(['chat_id' => $chatId, 'text' => $text, 'reply_to_message_id' => $messageId]);
    }

    private function answerCallbackQuery(array $message, string $text = ''): void
    {
        if (empty($message['id'])) {
            return;
        }

        $payload = ['callback_query_id' => $message['id']];
        if ($text !== '') {
            $payload['text'] = $text;
            $payload['show_alert'] = true;
        }
        $this->telegram->answerCallbackQuery($payload);
    }

    private function chatId(array $message): int
    {
        return intval($message['message']['chat']['id'] ?? 0);
    }

    private function messageId(array $message): int
    {
        return intval($message['message']['message_id'] ?? 0);
    }

    private function telegramUserId(array $message): int
    {
        return intval($message['from']['id'] ?? 0);
    }
}

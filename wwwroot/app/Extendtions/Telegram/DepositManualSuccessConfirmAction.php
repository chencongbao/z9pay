<?php

namespace App\Extendtions\Telegram;

use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Telegram\TelegramInstanceService;
use App\Services\Telegram\TelegramManagerService;
use App\Services\DepositOrder\AdminManualSuccessService;

class DepositManualSuccessConfirmAction
{
    public $telegram;

    public $keyboard = [
        'inline_keyboard' => []
    ];

    public function __construct($telegram = null)
    {
        $this->telegram = $telegram ?: app(TelegramInstanceService::class)->excute();
    }

    public function callback(array $data = [], array $message = []): void
    {
        $token = (string) ($data['k'] ?? '');
        if ($token === '') {
            return;
        }

        $cacheKey = $this->getCacheKey($token);
        $payload = Cache::get($cacheKey, []);
        if (empty($payload)) {
            $this->clearKeyboard($message);
            $this->replyText($message, '该人工补单确认已过时，请重新发起');
            return;
        }

        if (!app(TelegramManagerService::class)->isSuperManagerMessage($message)) {
            $this->replyNoPermission($message);
            return;
        }

        if ($this->isExpired($payload)) {
            $payload = $this->updatePayloadStatus($cacheKey, $payload, 'expired', ['expired_at' => now()->toDateTimeString()]);
            $this->forgetOrderPendingConfirm($payload);
            $this->clearKeyboard($message);
            $this->replyText($message, $this->buildBaseText($payload) . "\n\n状态：已过时\n过期时间：" . ($payload['expires_at'] ?? '-'));
            return;
        }

        if (($payload['status'] ?? '') !== 'pending') {
            $this->replyText($message, $this->handledStatusText((string) ($payload['status'] ?? '')));
            return;
        }

        $action = (string) ($data['a'] ?? '');
        if ($action === 'x') {
            $payload = $this->updatePayloadStatus($cacheKey, $payload, 'rejected', [
                'rejected_by' => $this->buildOperatorName($message),
                'rejected_at' => now()->toDateTimeString(),
            ]);
            $this->forgetOrderPendingConfirm($payload);
            $this->clearKeyboard($message);
            $this->replyText($message, $this->buildBaseText($payload) . "\n\n状态：已拒绝\n操作人：" . $payload['rejected_by']);
            return;
        }

        if ($action !== 'c') {
            return;
        }

        $lockKey = $cacheKey . ':lock';
        if (!Cache::add($lockKey, 1, now()->addSeconds(10))) {
            return;
        }

        try {
            $payload = $this->updatePayloadStatus($cacheKey, $payload, 'confirmed', [
                'confirmed_by' => $this->buildOperatorName($message),
                'confirmed_at' => now()->toDateTimeString(),
            ]);

            $this->clearKeyboard($message);
            $this->replyText($message, $this->buildBaseText($payload) . "\n\n状态：已确认，正在执行补单\n确认人：" . $payload['confirmed_by']);

            $order = app(AdminManualSuccessService::class)->excute(
                intval($payload['order_id'] ?? 0),
                floatval($payload['actual_amount'] ?? 0),
                (string) ($payload['remark'] ?? ''),
                intval($payload['admin_id'] ?? 0),
                (string) ($payload['admin_name'] ?? ''),
                (string) ($payload['confirmed_by'] ?? '')
            );

            $payload = $this->updatePayloadStatus($cacheKey, $payload, 'executed', [
                'executed_at' => now()->toDateTimeString(),
                'success_time' => $order->success_time ? date('Y-m-d H:i:s', $order->success_time) : '',
            ]);
            $this->forgetOrderPendingConfirm($payload);
            $this->sendMessage($message, $this->buildBaseText($payload) . "\n\n状态：执行成功\n确认人：" . ($payload['confirmed_by'] ?? '-') . "\n成功时间：" . ($payload['success_time'] ?: '-'));
        } catch (Throwable $e) {
            $payload = $this->updatePayloadStatus($cacheKey, $payload, 'failed', [
                'failed_at' => now()->toDateTimeString(),
                'failed_message' => $e->getMessage(),
            ]);
            $this->forgetOrderPendingConfirm($payload);
            $this->sendMessage($message, $this->buildBaseText($payload) . "\n\n状态：执行失败\n确认人：" . ($payload['confirmed_by'] ?? '-') . "\n失败原因：" . $e->getMessage());
        } finally {
            Cache::forget($lockKey);
        }
    }

    protected function getCacheKey(string $token): string
    {
        return CacheConstPrefixService::ADMIN_DEPOSIT_MANUAL_SUCCESS_CONFIRM . $token;
    }

    protected function clearKeyboard(array $message = []): void
    {
        $this->telegram->editMessageReplyMarkup([
            'chat_id' => $this->chatId($message),
            'message_id' => $this->messageId($message),
            'reply_markup' => json_encode($this->keyboard),
        ]);
    }

    protected function replyText(array $message = [], string $text = ''): void
    {
        $this->telegram->editMessageText([
            'chat_id' => $this->chatId($message),
            'message_id' => $this->messageId($message),
            'text' => $text,
        ]);
    }

    protected function buildOperatorName(array $message = []): string
    {
        $from = $message['from'] ?? [];
        $name = trim((string) (($from['username'] ?? '') !== '' ? '@' . $from['username'] : trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''))));

        return $name !== '' ? $name : ('TG#' . intval($from['id'] ?? 0));
    }

    protected function buildBaseText(array $payload = []): string
    {
        return implode("\n", [
            '代收人工补单确认',
            '',
            '商户订单号：' . ($payload['order_no'] ?? '-'),
            '平台订单号：' . ($payload['ordernumber'] ?? '-'),
            '商户：' . ($payload['merchant_name'] ?? '-'),
            '币种：' . ($payload['currency_name'] ?? '-'),
            '当前状态：' . ($payload['order_status'] ?? '-'),
            '提交金额：' . bob_unit_format($payload['amount'] ?? 0),
            '订单金额：' . bob_unit_format($payload['pay_amount'] ?? 0),
            '实付金额：' . bob_unit_format($payload['actual_amount'] ?? 0),
            '备注：' . ($payload['remark'] ?? '-'),
            '申请人：【#' . intval($payload['admin_id'] ?? 0) . '】' . ($payload['admin_name'] ?? '-'),
            '申请时间：' . ($payload['created_at'] ?? '-'),
            '过期时间：' . ($payload['expires_at'] ?? '-'),
        ]);
    }

    protected function isExpired(array $payload = []): bool
    {
        $expiresAt = trim((string) ($payload['expires_at'] ?? ''));
        if ($expiresAt === '') {
            return false;
        }

        return now()->greaterThanOrEqualTo(Carbon::parse($expiresAt));
    }

    protected function handledStatusText(string $status): string
    {
        return match ($status) {
            'executed' => '该人工补单已执行，请勿重复确认',
            'failed' => '该人工补单已执行失败，请勿重复确认',
            'rejected' => '该人工补单已被拒绝',
            'expired' => '该人工补单已过时，请重新发起',
            default => '该人工补单已处理',
        };
    }

    protected function updatePayloadStatus(string $cacheKey, array $payload, string $status, array $extra = []): array
    {
        $payload = array_merge($payload, ['status' => $status], $extra);
        Cache::put($cacheKey, $payload, now()->addMinutes(30));

        return $payload;
    }

    protected function forgetOrderPendingConfirm(array $payload = []): void
    {
        $orderId = intval($payload['order_id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        Cache::forget(CacheConstPrefixService::ADMIN_DEPOSIT_MANUAL_SUCCESS_CONFIRM_ORDER . $orderId);
    }

    protected function replyNoPermission(array $message = []): void
    {
        $operatorName = $this->buildOperatorName($message);
        $operatorId = intval($message['from']['id'] ?? 0);
        $this->telegram->answerCallbackQuery(['callback_query_id' => $message['id'] ?? '', 'text' => '你没有人工补单确认权限', 'show_alert' => true]);
        $this->sendMessage($message, "无权限：{$operatorName}(TG#{$operatorId}) 尝试确认/拒绝人工补单，但其不在飞机超级管理员名单内");
    }

    protected function sendMessage(array $message = [], string $text = ''): void
    {
        $this->telegram->sendMessage(['chat_id' => $this->chatId($message), 'text' => $text, 'reply_to_message_id' => $this->messageId($message)]);
    }

    protected function chatId(array $message = []): int
    {
        return intval($message['message']['chat']['id'] ?? 0);
    }

    protected function messageId(array $message = []): int
    {
        return intval($message['message']['message_id'] ?? 0);
    }
}

<?php

namespace App\Extendtions\Telegram;

use Throwable;
use Carbon\Carbon;
use App\Models\MerchantInfo;
use App\Models\MerchantPayment;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Common\SystemLogService;
use App\Services\Telegram\TelegramInstanceService;
use App\Services\Telegram\TelegramManagerService;
use App\Services\Telegram\TelegramOperatorService;
use App\Services\Merchant\MerchantBalanceChangeService;
use App\Jobs\MerchantBalanceJiaJianNoticeTelegramGroupJob;

class MerchantBalanceAddConfirmAction
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
            $this->replyText($message, '该商户人工加项确认已过时，请重新发起');
            return;
        }

        if (!app(TelegramManagerService::class)->isSuperManagerMessage($message)) {
            $this->replyNoPermission($message);
            return;
        }

        if ($this->isExpired($payload)) {
            $payload = $this->updatePayloadStatus($cacheKey, $payload, 'expired', ['expired_at' => now()->toDateTimeString()]);
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
            $confirmAdmin = app(TelegramOperatorService::class)->admin($message);
            $payload = $this->updatePayloadStatus($cacheKey, $payload, 'confirmed', [
                'confirmed_by' => $this->buildOperatorName($message),
                'confirmed_at' => now()->toDateTimeString(),
            ]);

            $merchant = MerchantInfo::where('merchant_user_id', $payload['merchant_user_id'] ?? 0)->first(['merchant_user_id', 'name', 'coder']);
            if (!$merchant) {
                throw new \Exception('商户不存在');
            }

            $amount = floatval($payload['amount'] ?? 0);
            if ($amount <= 0) {
                throw new \Exception('加项金额不合法');
            }

            $balancePaymentId = $this->getBalancePaymentId($payload);

            $merchantBalanceChangeService = App::make(MerchantBalanceChangeService::class);
            $merchantBalanceChangeService->excute([
                'mid' => $merchant->merchant_user_id,
                'amount' => $amount,
                'fee' => floatval($payload['fee'] ?? 0),
                'type' => 11,
                'admin_id' => intval($payload['admin_id'] ?? 0),
                'type_id' => $merchant->merchant_user_id,
                'payment_id' => $balancePaymentId,
                'remark' => (string) ($payload['remark'] ?? ''),
            ]);
            if ($merchantBalanceChangeService->merchant_balance_log_id <= 0) {
                throw new \Exception('加项执行失败，未生成商户流水');
            }

            $payload = $this->updatePayloadStatus($cacheKey, $payload, 'executed', [
                'executed_at' => now()->toDateTimeString(),
                'merchant_balance_log_id' => $merchantBalanceChangeService->merchant_balance_log_id,
            ]);

            $operator = '申请人：' . $this->buildApplicantText($payload) . "\n" . '确认人：' . ($payload['confirmed_by'] ?? '-');
            dispatch(new MerchantBalanceJiaJianNoticeTelegramGroupJob(
                $merchant->merchant_user_id,
                $merchantBalanceChangeService->merchant_balance_log_id,
                $operator
            ))->onQueue('query');

            app(SystemLogService::class)->logAction(
                actionKey: 'merchant.balance.add.confirm',
                text: '确认 商户人工加项',
                subject: $merchant,
                properties: [
                    'merchant_user_id' => $merchant->merchant_user_id,
                    'amount' => floatval($payload['amount'] ?? 0),
                    'fee' => floatval($payload['fee'] ?? 0),
                    'payment_id' => $balancePaymentId,
                    'merchant_payment_id' => intval($payload['payment_id'] ?? 0),
                    'remark' => (string) ($payload['remark'] ?? ''),
                    'apply_admin_id' => intval($payload['admin_id'] ?? 0),
                    'apply_admin_name' => (string) ($payload['admin_name'] ?? ''),
                    'confirmed_by' => (string) ($payload['confirmed_by'] ?? ''),
                    'merchant_balance_log_id' => $merchantBalanceChangeService->merchant_balance_log_id,
                ],
                remark: sprintf('确认商户人工加项 %.2f', floatval($payload['amount'] ?? 0)),
                logType: 'operation',
                actionMethod: 'POST',
                appType: 'admin',
                user: $confirmAdmin
            );

            $this->clearKeyboard($message);
            $this->replyText($message, $this->buildBaseText($payload) . "\n\n状态：已确认执行\n确认人：" . ($payload['confirmed_by'] ?? '-'));
        } catch (Throwable $e) {
            $payload = $this->updatePayloadStatus($cacheKey, $payload, 'failed', [
                'failed_at' => now()->toDateTimeString(),
                'failed_message' => $e->getMessage(),
            ]);
            $this->sendMessage($message, $this->buildBaseText($payload) . "\n\n状态：执行失败\n失败原因：" . $e->getMessage());
        } finally {
            Cache::forget($lockKey);
        }
    }

    protected function getCacheKey(string $token): string
    {
        return 'admin:merchant_balance:add_confirm:' . $token;
    }

    protected function getBalancePaymentId(array $payload = []): int
    {
        if (isset($payload['balance_payment_id'])) {
            return intval($payload['balance_payment_id']);
        }

        $merchantPaymentId = intval($payload['payment_id'] ?? 0);
        if ($merchantPaymentId <= 0) {
            return 0;
        }

        return intval(MerchantPayment::where('id', $merchantPaymentId)->value('payment_id') ?: 0);
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
            '商户人工加项确认',
            '',
            '商户名称：' . ($payload['merchant_name'] ?? '-'),
            '商户代码：' . ($payload['merchant_code'] ?? '-'),
            '加项金额：+' . bob_unit_format($payload['amount'] ?? 0),
            '手续费：' . bob_unit_format($payload['fee'] ?? 0),
            '备注：' . ($payload['remark'] ?? '-'),
            '申请人：' . $this->buildApplicantText($payload),
            '申请时间：' . ($payload['created_at'] ?? '-'),
            '过期时间：' . ($payload['expires_at'] ?? '-'),
        ]);
    }

    protected function buildApplicantText(array $payload = []): string
    {
        $adminId = intval($payload['admin_id'] ?? 0);
        $adminName = (string) ($payload['admin_name'] ?? '-');

        return $adminId > 0 ? '【#' . $adminId . '】' . $adminName : $adminName;
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
            'executed' => '该商户人工加项已执行，请勿重复确认',
            'failed' => '该商户人工加项已执行失败，请勿重复确认',
            'rejected' => '该商户人工加项已被拒绝',
            'expired' => '该商户人工加项已过时，请重新发起',
            default => '该商户人工加项已处理',
        };
    }

    protected function updatePayloadStatus(string $cacheKey, array $payload, string $status, array $extra = []): array
    {
        $payload = array_merge($payload, ['status' => $status], $extra);
        Cache::put($cacheKey, $payload, now()->addMinutes(30));

        return $payload;
    }

    protected function replyNoPermission(array $message = []): void
    {
        $operatorName = $this->buildOperatorName($message);
        $operatorId = intval($message['from']['id'] ?? 0);
        $this->telegram->answerCallbackQuery(['callback_query_id' => $message['id'] ?? '', 'text' => '你没有商户人工加项确认权限', 'show_alert' => true]);
        $this->sendMessage($message, "无权限：{$operatorName}(TG#{$operatorId}) 尝试确认/拒绝商户人工加项，但其不在飞机超级管理员名单内");
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

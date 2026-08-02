<?php

namespace App\Extendtions\Telegram;

use Illuminate\Support\Str;
use App\Models\MerchantInfo;
use App\Jobs\TelegramQunSendJob;
use App\Traits\TelegramTrait;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Telegram\TelegramOperatorService;
use App\Services\Merchant\MerchantBalanceChangeService;
use App\Jobs\MerchantBalanceJiaJianNoticeTelegramGroupJob;

class RechargeAction
{
    use TelegramTrait;

    private const MAX_RECHARGE_AMOUNT = 999999999999;

    protected $telegram;

    protected array $emptyKeyboard = ['inline_keyboard' => []];

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    protected function getTelegramOperatorName(array $message = []): string
    {
        $from = $message['from'] ?? [];
        $parts = array_filter([
            isset($from['first_name']) ? trim((string)$from['first_name']) : '',
            isset($from['last_name']) ? trim((string)$from['last_name']) : '',
        ]);

        $displayName = trim(implode('', $parts));
        if ($displayName === '') {
            $displayName = trim((string)($from['username'] ?? ''));
        }

        $displayName = $displayName !== '' ? $displayName : 'Telegram管理员';
        $fromId = (string)($from['id'] ?? 0);

        return '【TG#' . $fromId . '】' . $displayName;
    }

    public function confirmRecharge($message = [], $value = 0): void
    {
        Cache::put($this->operatorActionKey($message, 'cz'), 1, now()->addMinutes(30));
        $this->sendMessage($message, '确定充值：【<code><b>' . bob_unit_format($value) . '</b></code>】', $this->buildConfirmKeyboard(1, '确认充值', '取消充值', $value));
    }

    public function callbackRecharge($data = [], $message = [], $merchant_user_id = 0): void
    {
        $this->handleCallback($data, $message, intval($merchant_user_id), 'cz', 'cz_confirm', 11, '通过命令方式充值', '充值', 1);
    }

    public function recharge($message = [], $group_type = 0): void
    {
        $text = (string)($message['text'] ?? '');
        if (!$this->isRechargeCommand($text)) {
            return;
        }

        $value = $this->parseAmount($text, ['充值', 'cz', 'CZ', '加项', '+'], true);
        if ($value <= 0 || $value > self::MAX_RECHARGE_AMOUNT || !$this->canOperateMerchantBalance($message, intval($group_type))) {
            return;
        }

        $this->confirmRecharge($message, $value);
    }

    public function jianxiang($message = [], $group_type = 0): void
    {
        $text = (string)($message['text'] ?? '');
        if (mb_substr($text, 0, 2) !== '减项' || !$this->canOperateMerchantBalance($message, intval($group_type))) {
            return;
        }

        $value = $this->parseAmount($text, ['减项', '-']);
        if ($value > 0) {
            $this->confirmJianxiang($message, $value);
        }
    }

    public function confirmJianxiang($message = [], $value = 0): void
    {
        Cache::put($this->operatorActionKey($message, 'jx'), 1, now()->addMinutes(30));
        $this->sendMessage($message, '确定减项：【<code><b>' . bob_unit_format($value) . '</b></code>】', $this->buildConfirmKeyboard(3, '确认减项', '取消减项', $value));
    }

    public function callbackJianxiang($data = [], $message = [], $merchant_user_id = 0): void
    {
        $this->handleCallback($data, $message, intval($merchant_user_id), 'jx', 'jx_confirm', 12, '通过命令方式减项', '减项', -1);
    }

    private function canOperateMerchantBalance(array $message, int $groupType): bool
    {
        if ($groupType === 2) {
            $this->sendMessage($message, '金主群无法操作此命令', [], false);
            return false;
        }

        if (!$this->getMerchantUserId($message)) {
            $this->sendMessage($message, '群组未绑定商家,请输入<b>【<code>bd</code>+商户代码】</b>进行绑定');
            return false;
        }

        if (!$this->checkIsManager($message)) {
            $this->sendMessage($message, '您不是管理员，无权操作此命令');
            return false;
        }

        return true;
    }

    private function handleCallback(array $data, array $message, int $merchantUserId, string $actionSuffix, string $confirmSuffix, int $type, string $remark, string $actionText, int $direction): void
    {
        if ($merchantUserId <= 0) {
            return;
        }

        $action = (string)($data['action'] ?? '');
        if (!in_array($action, ['confirm', 'cancel'], true)) {
            return;
        }

        $value = floatval($data['value'] ?? 0);
        if ($value <= 0) {
            return;
        }

        $actionKey = $this->operatorActionKey($message, $actionSuffix, true);
        if (!Cache::get($actionKey)) {
            $this->answerCallbackAlert($message, '您不是命令发起人，无权操作此按钮');
            return;
        }

        if ($action === 'confirm') {
            $key = $this->operatorActionKey($message, $confirmSuffix, true);
            if (!Cache::add($key, 1, now()->addSeconds(5))) {
                return;
            }

            if ($direction > 0 && $this->shouldSendAddBalanceConfirm($value)) {
                if ($this->sendAddBalanceConfirm($merchantUserId, $value, $remark, $message)) {
                    $this->editCallbackText($message, '已发送商户人工加项确认，请等待超级管理员确认后生效【' . bob_unit_format($value) . '】');
                }
                Cache::forget($actionKey);
                return;
            }

            $this->changeMerchantBalance($merchantUserId, $value * $direction, $type, $this->buildBalanceRemark($remark, $value, $message), $message);
            $this->clearCallbackKeyboard($message);
        }

        if ($action === 'cancel') {
            $this->clearCallbackKeyboard($message);
            $this->editCallbackText($message, '您已取消' . $actionText . '【' . bob_unit_format($value) . '】');
        }

        Cache::forget($actionKey);
    }

    private function buildBalanceRemark(string $remark, float $value, array $message): string
    {
        $operator = app(TelegramOperatorService::class)->context($message);
        $adminText = $operator['admin_id'] > 0
            ? "#{$operator['admin_id']} {$operator['admin_name']}({$operator['admin_username']})"
            : '未匹配后台管理员';

        return sprintf(
            '%s；操作人：%s；TG：%s',
            $remark,
            $adminText,
            $operator['telegram_user_id'] . ' ' . $operator['telegram_name']
        );
    }

    private function changeMerchantBalance(int $merchantUserId, float $amount, int $type, string $remark, array $message): void
    {
        $merchantBalanceChangeService = App::make(MerchantBalanceChangeService::class);
        $operator = app(TelegramOperatorService::class)->context($message);
        $merchantBalanceChangeService->excute([
            'mid' => $merchantUserId,
            'amount' => $amount,
            'fee' => 0,
            'type' => $type,
            'type_id' => $merchantUserId,
            'payment_id' => 0,
            'remark' => $remark,
            'admin_id' => $operator['admin_id'],
        ]);

        dispatch(new MerchantBalanceJiaJianNoticeTelegramGroupJob($merchantUserId, $merchantBalanceChangeService->merchant_balance_log_id, $this->getTelegramOperatorName($message)))->onQueue('query');
    }

    private function shouldSendAddBalanceConfirm(float $amount): bool
    {
        if (intval(bob_admin_setting('merchant_balance_adjust_confirm_on')) !== 1) {
            return false;
        }

        $minAmount = max(0, floatval(bob_admin_setting('merchant_balance_adjust_confirm_min_amount')));
        return $minAmount <= 0 || $amount >= $minAmount;
    }

    private function sendAddBalanceConfirm(int $merchantUserId, float $amount, string $remark, array $message): bool
    {
        if (intval(bob_admin_setting('telegram_turn_on')) === 0) {
            $this->sendCallbackMessage($message, '飞机机器人未开启，无法发送商户人工加项确认');
            return false;
        }

        $groupId = trim((string)bob_admin_setting('merchant_balance_adjust_confirm_telegram_group_id'));
        if ($groupId === '') {
            $this->sendCallbackMessage($message, '商户人工加项确认通知群ID未配置');
            return false;
        }

        $merchant = MerchantInfo::where('merchant_user_id', $merchantUserId)->first(['merchant_user_id', 'name', 'coder']);
        if (!$merchant) {
            $this->sendCallbackMessage($message, '商户不存在，无法发送商户人工加项确认');
            return false;
        }

        $expireMinutes = max(1, intval(bob_admin_setting('merchant_balance_adjust_confirm_expire_minutes')) ?: 30);
        $token = Str::random(32);
        $payload = [
            'status' => 'pending',
            'merchant_user_id' => $merchant->merchant_user_id,
            'merchant_name' => $merchant->name,
            'merchant_code' => $merchant->coder,
            'amount' => $amount,
            'fee' => 0,
            'payment_id' => 0,
            'balance_payment_id' => 0,
            'pay_rate' => 0,
            'remark' => $this->buildBalanceRemark($remark, $amount, $message),
            'admin_id' => app(TelegramOperatorService::class)->adminId($message),
            'admin_name' => $this->getTelegramOperatorName($message),
            'created_at' => now()->toDateTimeString(),
            'expires_at' => now()->addMinutes($expireMinutes)->toDateTimeString(),
        ];

        Cache::put($this->addBalanceConfirmCacheKey($token), $payload, now()->addMinutes($expireMinutes + 30));

        dispatch(new TelegramQunSendJob([
            'telegram_group_id' => $groupId,
            'send_content' => $this->buildAddBalanceConfirmText($payload),
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => '确认加项', 'callback_data' => json_encode(['t' => 23, 'a' => 'c', 'k' => $token])],
                        ['text' => '拒绝加项', 'callback_data' => json_encode(['t' => 23, 'a' => 'x', 'k' => $token])],
                    ],
                ],
            ],
        ]))->onQueue('notice');

        return true;
    }

    private function buildAddBalanceConfirmText(array $payload): string
    {
        return implode("\n", [
            '📢 <b>商户人工加项确认</b>',
            '',
            '来源：<b>飞机命令</b>',
            '商户名称：<b>' . e((string)($payload['merchant_name'] ?? '-')) . '</b>',
            '商户代码：<code>' . e((string)($payload['merchant_code'] ?? '-')) . '</code>',
            '加项金额：<code>+' . bob_unit_format($payload['amount'] ?? 0) . '</code>',
            '手续费：<code>' . bob_unit_format($payload['fee'] ?? 0) . '</code>',
            '备注：' . e((string)($payload['remark'] ?? '-')),
            '申请人：<b>' . e((string)($payload['admin_name'] ?? '-')) . '</b>',
            '申请时间：' . ($payload['created_at'] ?? '-'),
            '过期时间：' . ($payload['expires_at'] ?? '-'),
            '',
            '请确认是否执行本次商户余额加项。',
        ]);
    }

    private function addBalanceConfirmCacheKey(string $token): string
    {
        return 'admin:merchant_balance:add_confirm:' . $token;
    }

    private function isRechargeCommand(string $text): bool
    {
        return self::matchesRechargeCommand($text);
    }

    public static function matchesRechargeCommand(string $text): bool
    {
        if (preg_match('/^(?:(?:充值|加项)|cz)\s*\+?\s*((?=.*\d)[\d\s()+\-*\/.]+)$/u', trim($text), $matches) !== 1) {
            return false;
        }

        $amount = preg_replace('/\s+/', '', (string)($matches[1] ?? ''));
        if (!is_numeric($amount)) {
            return true;
        }

        return floatval($amount) > 0 && floatval($amount) <= self::MAX_RECHARGE_AMOUNT;
    }

    private function parseAmount(string $text, array $search, bool $allowCalculate = false): float
    {
        $value = trim(str_replace($search, '', bob_replacement_empty($text)));
        if (is_numeric($value)) {
            return floatval($value);
        }

        if ($allowCalculate) {
            $result = $this->doCalculate($value);
            if (is_numeric($result)) {
                return floatval($result);
            }
        }

        return 0;
    }

    private function buildConfirmKeyboard(int $type, string $confirmText, string $cancelText, $value): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => $confirmText, 'callback_data' => json_encode(['type' => $type, 'action' => 'confirm', 'value' => $value])],
                    ['text' => $cancelText, 'callback_data' => json_encode(['type' => $type, 'action' => 'cancel', 'value' => $value])],
                ],
            ],
        ];
    }

    private function sendMessage(array $message, string $text, array $keyboard = [], bool $html = true): void
    {
        $chatId = $message['chat']['id'] ?? 0;
        if (!$chatId) {
            return;
        }

        $payload = ['chat_id' => $chatId, 'text' => $text];
        if ($html) {
            $payload['parse_mode'] = 'html';
        }
        if (!empty($message['message_id'])) {
            $payload['reply_to_message_id'] = $message['message_id'];
        }
        if (!empty($keyboard)) {
            $payload['reply_markup'] = json_encode($keyboard);
        }

        $this->telegram->sendMessage($payload);
    }

    private function sendCallbackMessage(array $message, string $text): void
    {
        $callbackMessage = $message['message'] ?? [];
        $this->sendMessage($callbackMessage, $text);
    }

    private function clearCallbackKeyboard(array $message): void
    {
        $chatId = $message['message']['chat']['id'] ?? 0;
        $messageId = $message['message']['message_id'] ?? 0;
        if (!$chatId || !$messageId) {
            return;
        }

        $this->telegram->editMessageReplyMarkup(['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => json_encode($this->emptyKeyboard)]);
    }

    private function editCallbackText(array $message, string $text): void
    {
        $chatId = $message['message']['chat']['id'] ?? 0;
        $messageId = $message['message']['message_id'] ?? 0;
        if (!$chatId || !$messageId) {
            return;
        }

        $this->telegram->editMessageText(['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text]);
    }

    private function operatorActionKey(array $message, string $suffix, bool $callback = false): string
    {
        $chatId = $callback ? ($message['message']['chat']['id'] ?? 0) : ($message['chat']['id'] ?? 0);

        return ($message['from']['id'] ?? 0) . $chatId . '_' . $suffix;
    }
}

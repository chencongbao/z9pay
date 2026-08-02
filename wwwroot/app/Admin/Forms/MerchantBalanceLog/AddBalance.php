<?php

namespace App\Admin\Forms\MerchantBalanceLog;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use Illuminate\Support\Str;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Form;
use App\Models\MerchantPayment;
use App\Rules\DecimalTwoPlaces;
use App\Jobs\TelegramQunSendJob;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\Merchant\MerchantBalanceChangeService;
use App\Jobs\MerchantBalanceJiaJianNoticeTelegramGroupJob;

class AddBalance extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $mid = intval($this->payload['mid'] ?? 0);
            $remark = trim((string) ($input['remark'] ?? ''));
            $amount = bob_amount_format($input['amount'] ?? 0);
            $paymentId = intval($input['payment_id'] ?? 0);
            $payRate = floatval($input['pay_rate'] ?? 0);

            if ($amount <= 0) {
                throw new RuntimeException('金额必须大于0');
            }

            app(AdminGoogle2faService::class)->verify($input['google_2fa_code'] ?? null);

            $merchant = $this->getMerchant($mid);
            if (!$merchant) {
                throw new RuntimeException('非法操作');
            }

            $payment = $this->resolvePayment($paymentId, $payRate, $amount, $merchant->merchant_user_id);

            if ($this->shouldSendConfirm($amount)) {
                $this->sendConfirmMessage($merchant, $amount, $payment, $remark, $admin);

                return $this->response()->success('已发送加项确认，请到配置的飞机群确认后生效。')->refresh();
            }

            $merchantBalanceLogId = DB::transaction(function () use ($merchant, $amount, $payment, $remark, $admin) {
                $merchantBalanceChangeService = App::make(MerchantBalanceChangeService::class);
                $result = $merchantBalanceChangeService->excute([
                    'mid' => $merchant->merchant_user_id,
                    'amount' => $amount,
                    'fee' => $payment['fee'],
                    'type' => 11,
                    'admin_id' => $admin->id,
                    'type_id' => $merchant->merchant_user_id,
                    'payment_id' => $payment['balance_payment_id'],
                    'remark' => $remark,
                ]);

                if (empty($result['success'])) {
                    throw new RuntimeException($result['message'] ?? '商户余额加项失败');
                }

                $desc = sprintf('手动增项 商户余额 %.2f', $amount);
                app(SystemLogService::class)->logAction(
                    actionKey: 'merchant.balance.add',
                    text: '手动增项 商户余额',
                    subject: $merchant,
                    properties: [
                        'merchant_user_id' => $merchant->merchant_user_id,
                        'amount' => $amount,
                        'fee' => $payment['fee'],
                        'payment_id' => $payment['balance_payment_id'],
                        'merchant_payment_id' => $payment['merchant_payment_id'],
                        'remark' => $remark,
                    ],
                    remark: $desc,
                    logType: 'operation',
                    actionMethod: 'POST',
                    appType: 'admin',
                    user: $admin
                );

                return $merchantBalanceChangeService->merchant_balance_log_id;
            });

            dispatch(new MerchantBalanceJiaJianNoticeTelegramGroupJob($merchant->merchant_user_id, $merchantBalanceLogId, '【#' . $admin->id . '】' . $admin->name))->onQueue('query');

            return $this->response()->success('操作成功.')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function resolvePayment(int $paymentId, float $payRate, float $amount, int $mid): array
    {
        $fee = 0;
        $balancePaymentId = 0;

        if ($paymentId > 0) {
            $payment = MerchantPayment::query()->whereKey($paymentId)->where('merchant_user_id', $mid)->first(['id', 'pay_rate', 'payment_id']);
            if (!$payment) {
                throw new RuntimeException('支付类型不存在');
            }

            $fee = bob_amount_format($payment->pay_rate * $amount / 100);
            $balancePaymentId = intval($payment->payment_id);
        }

        if ($paymentId === -1) {
            if ($payRate < 0 || $payRate > 100) {
                throw new RuntimeException('支付费率0-100');
            }

            $fee = bob_amount_format($payRate * $amount / 100);
        }

        return [
            'fee' => floatval($fee),
            'pay_rate' => $paymentId === -1 ? $payRate : 0,
            'payment_id' => $balancePaymentId,
            'balance_payment_id' => $balancePaymentId,
            'merchant_payment_id' => $paymentId,
        ];
    }

    protected function isConfirmEnabled(): bool
    {
        return intval(bob_admin_setting('merchant_balance_adjust_confirm_on')) === 1;
    }

    protected function getConfirmMinAmount(): float
    {
        return max(0, floatval(bob_admin_setting('merchant_balance_adjust_confirm_min_amount')));
    }

    protected function shouldSendConfirm(float $amount): bool
    {
        if (!$this->isConfirmEnabled()) {
            return false;
        }

        $minAmount = $this->getConfirmMinAmount();
        return $minAmount <= 0 || $amount >= $minAmount;
    }

    protected function sendConfirmMessage(MerchantInfo $merchant, float $amount, array $payment, string $remark, $admin): void
    {
        if (intval(bob_admin_setting('telegram_turn_on')) === 0) {
            throw new RuntimeException('飞机机器人未开启，无法发送加项确认');
        }

        $groupId = trim((string) bob_admin_setting('merchant_balance_adjust_confirm_telegram_group_id'));
        if ($groupId === '') {
            throw new RuntimeException('商户人工加项确认通知群ID未配置');
        }

        $expireMinutes = max(1, intval(bob_admin_setting('merchant_balance_adjust_confirm_expire_minutes')) ?: 30);
        $token = Str::random(32);
        $payload = [
            'status' => 'pending',
            'merchant_user_id' => $merchant->merchant_user_id,
            'merchant_name' => $merchant->name,
            'merchant_code' => $merchant->coder,
            'amount' => $amount,
            'fee' => $payment['fee'],
            'payment_id' => $payment['merchant_payment_id'],
            'balance_payment_id' => $payment['balance_payment_id'],
            'pay_rate' => $payment['pay_rate'],
            'remark' => $remark,
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'created_at' => now()->toDateTimeString(),
            'expires_at' => now()->addMinutes($expireMinutes)->toDateTimeString(),
        ];

        Cache::put($this->confirmCacheKey($token), $payload, now()->addMinutes($expireMinutes + 30));

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '确认加项', 'callback_data' => json_encode(['t' => 23, 'a' => 'c', 'k' => $token])],
                    ['text' => '拒绝加项', 'callback_data' => json_encode(['t' => 23, 'a' => 'x', 'k' => $token])],
                ],
            ],
        ];

        dispatch(new TelegramQunSendJob([
            'telegram_group_id' => $groupId,
            'send_content' => $this->buildConfirmText($payload),
            'reply_markup' => $keyboard,
        ]))->onQueue('notice');
    }

    protected function buildConfirmText(array $payload): string
    {
        return implode("\n", [
            '📢 <b>商户人工加项确认</b>',
            '',
            '商户名称：<b>' . e((string) ($payload['merchant_name'] ?? '-')) . '</b>',
            '商户代码：<code>' . e((string) ($payload['merchant_code'] ?? '-')) . '</code>',
            '加项金额：<code>+' . bob_unit_format($payload['amount'] ?? 0) . '</code>',
            '手续费：<code>' . bob_unit_format($payload['fee'] ?? 0) . '</code>',
            '备注：' . e((string) ($payload['remark'] ?? '-')),
            '申请人：<b>【#' . intval($payload['admin_id'] ?? 0) . '】' . e((string) ($payload['admin_name'] ?? '-')) . '</b>',
            '申请时间：' . ($payload['created_at'] ?? '-'),
            '过期时间：' . ($payload['expires_at'] ?? '-'),
            '',
            '请确认是否执行本次商户余额加项。',
        ]);
    }

    protected function confirmCacheKey(string $token): string
    {
        return 'admin:merchant_balance:add_confirm:' . $token;
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-balance-log-add');
    }

    public function form()
    {
        if ($this->isConfirmEnabled()) {
            $minAmount = $this->getConfirmMinAmount();
            $this->html(
                <<<HTML
<div class="alert alert-warning mb-3" style="margin-bottom:15px;">
    <i class="feather icon-alert-triangle"></i>
    当前已开启<strong>商户人工加项确认</strong>。
    <br>
    审核金额：增项金额<strong>大于等于{$minAmount}</strong>时需要审核；设置为<strong>0</strong>表示所有商户人工加项都需要审核。
</div>
HTML
            )->width(12, 0);
        }

        $this->display('name', '商户');
        $this->text('amount', '增项金额')->rules(['numeric', 'between:0,9999999999999999', new DecimalTwoPlaces()], ['numeric' => '增项金额不合法', 'between' => '增项金额不合法'])->required();

        $mid = intval($this->payload['mid'] ?? 0);
        $result = MerchantPayment::query()->where('merchant_user_id', $mid)->where('status', 1)->get(['id', 'pay_rate', 'payment_id']);
        $data = [];
        $data[0] = '无费率';
        $data[-1] = '自定义费率';
        if (!$result->isEmpty()) {
            foreach ($result as $item) {
                $data[$item->id] = $item->payment_name . '【增项费率：' . bob_amount_format($item->pay_rate) . '%】';
            }
        }

        $this->select('payment_id', '支付类型')->options($data)->disableClearButton()->when(-1, function () {
            $this->rate('pay_rate', '增项费率')->rules(['numeric', 'between:0,100', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '支付费率0-100'])->default(0);
        });

        $this->textarea('remark', '备注')->rules('required|max:200', ['required' => '备注必填', 'max' => '备注过长'])->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $mid = intval($this->payload['mid'] ?? 0);
        $merchant = $this->getMerchant($mid);

        return [
            'name' => optional($merchant)->name,
            'amount' => '',
            'remark' => '',
            'payment_id' => 0,
        ];
    }

    private function getMerchant(int $mid): ?MerchantInfo
    {
        if ($mid <= 0) {
            return null;
        }

        return MerchantInfo::query()->whereKey($mid)->first(['merchant_user_id', 'name', 'coder']);
    }
}

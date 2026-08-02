<?php

namespace App\Admin\Forms\DepositeOrder;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use Illuminate\Support\Str;
use App\Models\DepositOrder;
use Dcat\Admin\Widgets\Form;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\Cache;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Google\AdminGoogle2faService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Telegram\TelegramManagerService;
use App\Services\Telegram\TelegramInstanceService;
use App\Services\DepositOrder\AdminManualSuccessService;

class OrderSuccess extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            if ($admin->cannot('deposit-order-manual-success')) {
                throw new RuntimeException('无人工补单权限');
            }

            $id = intval($this->payload['id'] ?? 0);
            $remark = trim((string) ($input['remark'] ?? ''));
            $actualAmount = floatval($input['actual_amount'] ?? -1);

            if ($id <= 0) {
                throw new RuntimeException('订单参数错误');
            }

            if ($actualAmount < 0) {
                throw new RuntimeException('实付金额不合法');
            }

            app(AdminGoogle2faService::class)->verify($input['google_2fa_code'] ?? null);

            $shouldSendConfirm = $this->shouldSendConfirm($actualAmount);
            $order = $this->findOrderForHandle($id, $shouldSendConfirm);

            if (!$order) {
                throw new RuntimeException('订单不存在');
            }

            $this->assertCanSuccess($order);

            if ($shouldSendConfirm) {
                $this->sendTelegramConfirm($order, $actualAmount, $remark, $admin);
                return $this->response()->success('已发送人工补单确认，请到配置的飞机群确认后生效。')->refresh();
            }

            // 手动补单统一走成功服务，保证余额、日志、缓存和回调逻辑一致。
            app(AdminManualSuccessService::class)->excute($order->id, $actualAmount, $remark, $admin->id, $this->adminName($admin));

            return $this->response()->success('补单成功.')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('deposit-order-manual-success');
    }

    public function form()
    {
        $confirmOn = $this->confirmOn();
        $expireMinutes = $this->getConfirmExpireMinutes();
        $minAmount = $this->getConfirmMinAmount();

        $this->confirm(
            '订单成功',
            $confirmOn
                ? "当前已开启人工补单确认，实付金额达到{$minAmount}时会先发送到确认群等待飞机超级管理员确认，确认有效期{$expireMinutes}分钟。确定继续？"
                : '<span class="label" style="background:#21b978;">代收订单手动成功确认</span>'
        );

        if ($confirmOn) {
            $groupId = trim((string) bob_admin_setting('deposit_manual_success_confirm_telegram_group_id'));
            $this->html(
                <<<HTML
<div class="alert alert-warning mb-3" style="margin-bottom:15px;">
    <i class="feather icon-alert-triangle"></i>
    当前已开启<strong>人工补单确认</strong>，提交后不会立即补单，而是先发送到确认群等待<strong>飞机超级管理员</strong>确认。
    <br>
    审核金额：实付金额<strong>大于等于{$minAmount}</strong>时需要审核；设置为<strong>0</strong>表示所有人工补单都需要审核。
    <br>
    确认有效期为<strong>{$expireMinutes}分钟</strong>。
    {$this->buildGroupNoticeHtml($groupId)}
</div>
HTML
            )->width(12, 0);
        }

        $this->display('order_no', '商户订单号');
        $this->display('ordernumber', '平台订单号');
        $this->display('amount', '订单金额');
        $this->text('actual_amount', '实付金额')->rules(['numeric', 'between:0,999999999', new DecimalTwoPlaces()], ['numeric' => '实付金额不合法', 'between' => '实付金额不合法'])->required();
        $this->textarea('remark', '备注')->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = intval($this->payload['id'] ?? 0);
        $order = $this->findOrderForDefault($id);

        return [
            'order_no' => optional($order)->order_no,
            'ordernumber' => optional($order)->ordernumber,
            'amount' => optional($order)->amount,
            'actual_amount' => optional($order)->amount,
            'remark' => '',
            'google_2fa_code' => '',
        ];
    }

    protected function confirmOn(): bool
    {
        return intval(bob_admin_setting('deposit_manual_success_confirm_on')) === 1;
    }

    protected function getConfirmExpireMinutes(): int
    {
        return max(1, intval(bob_admin_setting('deposit_manual_success_confirm_expire_minutes')) ?: 30);
    }

    protected function getConfirmMinAmount(): float
    {
        return max(0, floatval(bob_admin_setting('deposit_manual_success_confirm_min_amount')));
    }

    protected function shouldSendConfirm(float $actualAmount): bool
    {
        if (!$this->confirmOn()) {
            return false;
        }

        $minAmount = $this->getConfirmMinAmount();
        return $minAmount <= 0 || $actualAmount >= $minAmount;
    }

    protected function findOrderForHandle(int $id, bool $withConfirmFields): ?DepositOrder
    {
        if ($id <= 0) {
            return null;
        }

        $query = DepositOrder::query();

        if (!$withConfirmFields) {
            return $query->whereKey($id)->first(['id', 'status']);
        }

        return $query
            ->with(['merchant_info:merchant_user_id,name,currency_id'])
            ->whereKey($id)
            ->first([
                'id',
                'order_no',
                'ordernumber',
                'mid',
                'currency_id',
                'status',
                'amount',
                'pay_amount',
            ]);
    }

    protected function findOrderForDefault(int $id): ?DepositOrder
    {
        if ($id <= 0) {
            return null;
        }

        return DepositOrder::query()->whereKey($id)->first(['id', 'order_no', 'ordernumber', 'amount']);
    }

    protected function assertCanSuccess(DepositOrder $order): void
    {
        if (intval($order->status) === 5) {
            throw new RuntimeException('订单已经成功，请勿重复处理');
        }
        if (intval($order->status) === 6) {
            throw new RuntimeException('订单已经失败，请勿重复处理');
        }
    }

    protected function sendTelegramConfirm(DepositOrder $order, float $actualAmount, string $remark, $admin): void
    {
        $groupId = trim((string) bob_admin_setting('deposit_manual_success_confirm_telegram_group_id'));
        $superManagerIds = app(TelegramManagerService::class)->superManagerIds();
        $expireMinutes = $this->getConfirmExpireMinutes();
        if ($groupId === '') {
            throw new RuntimeException('人工补单确认通知群未配置');
        }
        if (empty($superManagerIds)) {
            throw new RuntimeException('飞机超级管理员未配置');
        }

        $this->assertNoPendingConfirm($order);

        $token = Str::random(16);
        $ttl = now()->addMinutes($expireMinutes + 30);
        $payload = [
            'status' => 'pending',
            'token' => $token,
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'ordernumber' => $order->ordernumber,
            'merchant_name' => optional($order->merchant_info)->bname ?: optional($order->merchant_info)->name,
            'currency_name' => $this->getCurrencyName(intval($order->currency_id)),
            'order_status' => $this->getStatusName(intval($order->status)),
            'amount' => $order->amount,
            'pay_amount' => $order->pay_amount,
            'actual_amount' => $actualAmount,
            'remark' => $remark,
            'admin_id' => $admin->id,
            'admin_name' => $this->adminName($admin),
            'created_at' => now()->toDateTimeString(),
            'expires_at' => now()->addMinutes($expireMinutes)->toDateTimeString(),
            'expire_minutes' => $expireMinutes,
        ];
        Cache::put($this->getConfirmCacheKey($token), $payload, $ttl);
        Cache::put($this->getOrderConfirmCacheKey((int) $order->id), $token, $ttl);

        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '确认补单', 'callback_data' => json_encode(['t' => 24, 'a' => 'c', 'k' => $token])],
                ['text' => '拒绝补单', 'callback_data' => json_encode(['t' => 24, 'a' => 'x', 'k' => $token])],
            ]],
        ];

        app(TelegramInstanceService::class)->excute()->sendMessage([
            'chat_id' => $groupId,
            'text' => $this->buildConfirmMessage($payload),
            'reply_markup' => json_encode($keyboard),
        ]);
    }

    protected function assertNoPendingConfirm(DepositOrder $order): void
    {
        $orderCacheKey = $this->getOrderConfirmCacheKey((int) $order->id);
        $token = (string) Cache::get($orderCacheKey, '');
        if ($token === '') {
            return;
        }

        $payload = Cache::get($this->getConfirmCacheKey($token), []);
        if (empty($payload)) {
            Cache::forget($orderCacheKey);
            return;
        }

        if (($payload['status'] ?? '') !== 'pending') {
            Cache::forget($orderCacheKey);
            return;
        }

        $expiresAt = strtotime((string) ($payload['expires_at'] ?? ''));
        if ($expiresAt > 0 && $expiresAt <= time()) {
            Cache::forget($orderCacheKey);
            return;
        }

        throw new RuntimeException('该订单已有人工补单确认待处理，请等待管理员确认或过期后再重新发起');
    }

    protected function getConfirmCacheKey(string $token): string
    {
        return CacheConstPrefixService::ADMIN_DEPOSIT_MANUAL_SUCCESS_CONFIRM . $token;
    }

    protected function getOrderConfirmCacheKey(int $orderId): string
    {
        return CacheConstPrefixService::ADMIN_DEPOSIT_MANUAL_SUCCESS_CONFIRM_ORDER . $orderId;
    }

    protected function buildConfirmMessage(array $data = []): string
    {
        return implode("\n", [
            '代收人工补单确认',
            '',
            '商户订单号：' . ($data['order_no'] ?? '-'),
            '平台订单号：' . ($data['ordernumber'] ?? '-'),
            '商户：' . ($data['merchant_name'] ?? '-'),
            '币种：' . ($data['currency_name'] ?? '-'),
            '当前状态：' . ($data['order_status'] ?? '-'),
            '提交金额：' . bob_unit_format($data['amount'] ?? 0),
            '订单金额：' . bob_unit_format($data['pay_amount'] ?? 0),
            '实付金额：' . bob_unit_format($data['actual_amount'] ?? 0),
            '备注：' . ($data['remark'] ?? '-'),
            '申请人：【#' . intval($data['admin_id'] ?? 0) . '】' . ($data['admin_name'] ?? '-'),
            '申请时间：' . ($data['created_at'] ?? '-'),
            '确认时限：' . intval($data['expire_minutes'] ?? $this->getConfirmExpireMinutes()) . '分钟内有效',
            '过期时间：' . ($data['expires_at'] ?? '-'),
        ]);
    }

    protected function getCurrencyName(int $currencyId): string
    {
        $currency = collect(config('default.currency'))->firstWhere('id', $currencyId);

        return (string) ($currency['name'] ?? '');
    }

    protected function getStatusName(int $status): string
    {
        return (string) (config('default.deposite_status')[$status] ?? $status);
    }

    protected function buildGroupNoticeHtml(string $groupId = ''): string
    {
        if ($groupId === '') {
            return '<br><span class="text-danger">当前确认群未配置，请先到安全配置中完成配置。</span>';
        }

        return '<br><span class="text-muted">确认群：' . e($groupId) . '</span>';
    }

    protected function adminName($admin): string
    {
        return (string) ($admin->username ?: $admin->name);
    }
}

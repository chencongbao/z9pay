<?php

namespace App\Jobs;

use App\Models\DepositOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Order\OrderCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\SelfNewPayment\GetUserDaifukuanDepositOrderListService;

class DepositOrderTimeoutJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const TYPE_PAYMENT = 'payment';
    private const TYPE_CONFIRM = 'confirm';

    public $tries = 1;

    public $timeout = 120;

    public $uniqueFor = 600;

    public $order_id;

    public $timeout_type;

    public function __construct($order_id = 0, string $timeoutType = self::TYPE_PAYMENT)
    {
        $this->order_id = intval($order_id);
        $this->timeout_type = $timeoutType;
    }

    public function uniqueId(): string
    {
        return $this->timeout_type . ':' . $this->order_id;
    }

    public function handle()
    {
        $order = DepositOrder::query()->whereKey($this->order_id)->first(CacheConstPrefixService::CACHE_DEPOSIT_FILED);
        if (!$order || !$this->canTimeout($order)) {
            return;
        }

        // 按超时场景再次带条件更新，避免支付确认、成功回调并发时覆盖新状态。
        $updated = $this->timeoutQuery((int) $order->id)->update(['status' => 4, 'remark' => $this->timeoutRemark()]);
        if ($updated < 1) {
            return;
        }

        $order = DepositOrder::query()->whereKey($order->id)->first(CacheConstPrefixService::CACHE_DEPOSIT_FILED);
        if (!$order) {
            return;
        }

        bob_send_system_deposit_notice(['success_text' => '代收订单超时，订单号：' . $order->ordernumber, 'voice_id' => 'deposit_4', 'id' => 4]);
        if ($order->user_id > 0) {
            App::make(GetUserDaifukuanDepositOrderListService::class)->remove($order->user_id, $order);
        }

        App::make(OrderCacheService::class)->putDeposit($order);
    }

    private function canTimeout(DepositOrder $order): bool
    {
        if ($this->timeout_type === self::TYPE_CONFIRM) {
            return in_array((int) $order->status, [3, 7], true)
                && (int) $order->pay_status === 2
                && $this->confirmTimeExpired((int) $order->confirm_time);
        }

        return in_array((int) $order->status, [1, 3], true)
            && in_array((int) $order->pay_status, [1, 3], true)
            && $this->paymentTimeExpired((int) $order->expired_time);
    }

    private function timeoutQuery(int $orderId)
    {
        $query = DepositOrder::query()->whereKey($orderId);
        if ($this->timeout_type === self::TYPE_CONFIRM) {
            return $query->whereIn('status', [3, 7])
                ->where('pay_status', 2)
                ->where('confirm_time', '>', 0)
                ->where('confirm_time', '<=', $this->confirmTimeoutBefore());
        }

        return $query->whereIn('status', [1, 3])
            ->whereIn('pay_status', [1, 3])
            ->where('expired_time', '>', 0)
            ->where('expired_time', '<', time());
    }

    private function timeoutRemark(): string
    {
        return $this->timeout_type === self::TYPE_CONFIRM ? '订单确认超时' : '订单支付超时';
    }

    private function paymentTimeExpired(int $expiredTime): bool
    {
        return $expiredTime > 0 && $expiredTime < time();
    }

    private function confirmTimeExpired(int $confirmTime): bool
    {
        return $confirmTime > 0 && $confirmTime <= $this->confirmTimeoutBefore();
    }

    private function confirmTimeoutBefore(): int
    {
        $timeoutMinutes = max(1, intval(bob_admin_setting('base_deposit_confirm_overtime')));

        return time() - ($timeoutMinutes * 60);
    }
}

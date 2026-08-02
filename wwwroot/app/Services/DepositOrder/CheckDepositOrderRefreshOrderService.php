<?php

namespace App\Services\DepositOrder;

use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;

class CheckDepositOrderRefreshOrderService
{
    private const COUNT_PREFIX = 'deposit_refresh_order_count_';

    private const ORDER_PREFIX = 'deposit_refresh_order_order_';

    private const LOCK_SECONDS = 5;

    private const LOCK_WAIT_SECONDS = 2;

    public function excute($data = [], int $orderId = 0): bool
    {
        return (bool)($this->checkWithReason($data, $orderId)['triggered'] ?? false);
    }

    public function checkWithReason($data = [], int $orderId = 0): array
    {
        if (!intval(bob_admin_setting('base_deposit_refresh_order_switch'))) {
            return $this->result(false, '代收刷单开关关闭');
        }

        $dimensions = $this->refreshOrderDimensions();
        $items = $this->refreshOrderItems($data, $dimensions);
        if ($items === []) {
            return $this->result(false, '代收刷单维度未命中', [
                'dimensions' => $dimensions,
            ]);
        }

        $key = $this->refreshOrderKey($items);
        $cacheKey = self::COUNT_PREFIX . md5($key);
        $limit = max(1, intval(bob_admin_setting('base_deposit_refresh_order_number')));
        $ttl = max(1, (int) ceil(floatval(bob_admin_setting('base_deposit_refresh_order_time'))));
        $expiresAt = now()->addMinutes($ttl);

        if ($orderId <= 0) {
            $currentCount = intval(Cache::get($cacheKey, 0));

            return $this->result($currentCount >= $limit, $this->reason($items, $limit, $ttl, $currentCount), $this->context($items, $currentCount, $limit, $ttl));
        }

        $orderKey = self::ORDER_PREFIX . $orderId;

        // 同一组合串行登记，避免并发请求绕过计数限制或覆盖计数。
        try {
            return Cache::lock($this->lockKey($cacheKey), self::LOCK_SECONDS)->block(
                self::LOCK_WAIT_SECONDS,
                function () use ($cacheKey, $orderKey, $limit, $ttl, $expiresAt, $items): array {
                    if (Cache::has($orderKey)) {
                        return $this->result(false, '该订单已登记刷单计数', $this->context($items, null, $limit, $ttl));
                    }

                    $currentCount = intval(Cache::get($cacheKey, 0));
                    if ($currentCount >= $limit) {
                        return $this->result(true, $this->reason($items, $limit, $ttl, $currentCount), $this->context($items, $currentCount, $limit, $ttl));
                    }

                    if (!Cache::add($orderKey, $cacheKey, now()->addMinutes($ttl + 10))) {
                        return $this->result(false, '该订单刷单计数登记失败', $this->context($items, $currentCount, $limit, $ttl));
                    }

                    Cache::put($cacheKey, $currentCount + 1, $expiresAt);

                    return $this->result(false, '未达到代收刷单触发条件', $this->context($items, $currentCount + 1, $limit, $ttl));
                }
            );
        } catch (LockTimeoutException) {
            // 风控锁竞争超时时保守拦截，避免高并发绕过限制。
            return $this->result(true, '代收刷单风控锁竞争超时，系统保守拦截', $this->context($items, null, $limit, $ttl));
        }
    }

    public function release(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $orderKey = self::ORDER_PREFIX . $orderId;
        $cacheKey = Cache::get($orderKey);
        if (!is_string($cacheKey) || $cacheKey === '') {
            return;
        }

        // 与登记共用组合锁，避免同一订单被并发重复释放。
        try {
            Cache::lock($this->lockKey($cacheKey), self::LOCK_SECONDS)->block(
                self::LOCK_WAIT_SECONDS,
                function () use ($cacheKey, $orderKey): void {
                    if (Cache::get($orderKey) !== $cacheKey) {
                        return;
                    }

                    $currentCount = intval(Cache::get($cacheKey, 0));
                    if ($currentCount <= 1) {
                        Cache::forget($cacheKey);
                    } else {
                        Cache::decrement($cacheKey);
                    }

                    Cache::forget($orderKey);
                }
            );
        } catch (LockTimeoutException) {
            // 成功订单不能因风控计数释放超时而中断，保留 TTL 自动清理兜底。
        }
    }

    private function refreshOrderDimensions(): array
    {
        $setting = bob_admin_setting('base_deposit_refresh_order_key');
        if (is_string($setting)) {
            $setting = json_decode($setting, true) ?: [];
        }

        return array_values(array_unique(array_map('intval', is_array($setting) ? $setting : [])));
    }

    private function refreshOrderItems(array $data, array $dimensions): array
    {
        $items = [];

        if (in_array(1, $dimensions, true) && isset($data['mid'])) {
            $items['同商户'] = $data['mid'];
        }
        if (in_array(2, $dimensions, true) && !empty($data['ip'])) {
            $items['同IP'] = $data['ip'];
        }
        if (in_array(3, $dimensions, true) && !empty($data['pay_name'])) {
            $items['同姓名'] = $data['pay_name'];
        }
        if (in_array(4, $dimensions, true) && isset($data['amount'])) {
            $items['同金额'] = $data['amount'];
        }

        return $items;
    }

    private function refreshOrderKey(array $items): string
    {
        return 'deposit_refresh_order_' . implode('_', array_map('strval', $items));
    }

    private function reason(array $items, int $limit, int $ttl, int $currentCount): string
    {
        $dimensionText = implode('、', array_keys($items));

        return $ttl . '分钟内' . $dimensionText . '重复提交，已达到' . $currentCount . '/' . $limit . '单，触发代收刷单风控';
    }

    private function context(array $items, ?int $currentCount, int $limit, int $ttl): array
    {
        return [
            'matched_conditions' => $items,
            'window_minutes' => $ttl,
            'trigger_count' => $limit,
            'current_count' => $currentCount,
        ];
    }

    private function result(bool $triggered, string $reason, array $context = []): array
    {
        return [
            'triggered' => $triggered,
            'reason' => $reason,
            'context' => $context,
        ];
    }

    private function lockKey(string $cacheKey): string
    {
        return $cacheKey . ':lock';
    }
}

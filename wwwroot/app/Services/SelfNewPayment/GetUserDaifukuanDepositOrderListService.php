<?php

namespace App\Services\SelfNewPayment;

use Throwable;
use App\Models\DepositOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\User\UserPendingDepositOrderStatsService;

class GetUserDaifukuanDepositOrderListService
{
    private const CACHE_MINUTES = 5;
    private const PENDING_PAY_STATUSES = UserPendingDepositOrderStatsService::PENDING_PAY_STATUSES;

    public function excute($user_id = 0, $order = null, $force = false)
    {
        $userId = $this->userId($user_id);

        if ($force) {
            return $this->rebuild($userId);
        }

        if ($order) {
            return $this->syncByOrder($userId, $order);
        }

        return $this->get($userId);
    }

    public function get($user_id = 0): array
    {
        $userId = $this->userId($user_id);
        if ($userId <= 0) {
            return [];
        }

        $data = Cache::get($this->cacheKey($userId));
        if (is_array($data)) {
            return $data;
        }

        return $this->rebuild($userId);
    }

    public function add($user_id = 0, $order = null): array
    {
        $userId = $this->userId($user_id);
        if ($userId <= 0 || empty($order) || empty($order->id)) {
            return [];
        }

        return $this->withLock($userId, function () use ($userId, $order) {
            $data = collect($this->getCachedList($userId))
                ->reject(fn($item) => intval($item['id'] ?? 0) === intval($order->id))
                ->values()
                ->all();
            $data[] = $this->orderRow($order);

            return $this->put($userId, $data);
        });
    }

    public function remove($user_id = 0, $order = null): array
    {
        $userId = $this->userId($user_id);
        if ($userId <= 0 || empty($order) || empty($order->id)) {
            return [];
        }

        return $this->withLock($userId, function () use ($userId, $order) {
            $data = collect($this->getCachedList($userId))
                ->reject(fn($item) => intval($item['id'] ?? 0) === intval($order->id))
                ->values()
                ->all();

            return $this->put($userId, $data);
        });
    }

    public function syncByOrder($user_id = 0, $order = null): array
    {
        if (in_array(intval($order->status ?? 0), self::PENDING_PAY_STATUSES, true)) {
            return $this->add($user_id, $order);
        }

        return $this->remove($user_id, $order);
    }

    public function rebuild($user_id = 0): array
    {
        $userId = $this->userId($user_id);
        if ($userId <= 0) {
            return [];
        }

        return $this->withLock($userId, function () use ($userId) {
            return $this->put($userId, $this->getOrderList($userId));
        });
    }

    protected function getOrderList($user_id = 0): array
    {
        $userId = $this->userId($user_id);
        if ($userId <= 0) {
            return [];
        }

        return DepositOrder::where('user_id', $userId)
            ->whereIn('status', self::PENDING_PAY_STATUSES)
            ->orderByDesc('id')
            ->get(['id', 'amount', 'user_bank_id', 'created_at', 'user_id'])
            ->map(fn($order) => $this->orderRow($order))
            ->all();
    }

    private function getCachedList($user_id): array
    {
        $userId = $this->userId($user_id);
        $data = Cache::get($this->cacheKey($userId));
        if (is_array($data)) {
            return $data;
        }

        return $this->getOrderList($userId);
    }

    private function put($user_id, array $data): array
    {
        $userId = $this->userId($user_id);
        if ($userId <= 0) {
            return [];
        }

        $data = collect($data)
            ->filter(fn($item) => !empty($item['id']))
            ->unique(fn($item) => intval($item['id']))
            ->sortByDesc(fn($item) => intval($item['id']))
            ->values()
            ->all();
        Cache::put($this->cacheKey($userId), $data, now()->addMinutes(self::CACHE_MINUTES));
        $this->syncStats($userId, $data);

        return $data;
    }

    private function syncStats(int $userId, array $data): void
    {
        // 以缓存明细最终结果同步 users 汇总字段，避免重复 add/remove 导致金额双算。
        app(UserPendingDepositOrderStatsService::class)->replace($userId, collect($data)->sum('amount'), count($data));
    }

    private function orderRow($order): array
    {
        return [
            'id' => intval($order->id),
            'amount' => bob_amount_format($order->amount),
            'user_bank_id' => intval($order->user_bank_id),
            'created_at' => $order->created_at ? Carbon::parse($order->created_at)->format('Y-m-d H:i:s') : '',
            'user_id' => intval($order->user_id),
        ];
    }

    private function withLock($user_id, callable $callback): array
    {
        try {
            return Cache::lock($this->lockKey($user_id), 10)->block(2, $callback);
        } catch (Throwable $e) {
            return $callback();
        }
    }

    private function cacheKey($user_id): string
    {
        return CacheConstPrefixService::USER_DAIFUKUAN_DEPOSIT_ORDER_LIST . $this->userId($user_id);
    }

    private function lockKey($user_id): string
    {
        return CacheConstPrefixService::USER_DAIFUKUAN_DEPOSIT_ORDER_LIST_LOCK . $this->userId($user_id);
    }

    private function userId($user_id): int
    {
        return intval($user_id);
    }
}

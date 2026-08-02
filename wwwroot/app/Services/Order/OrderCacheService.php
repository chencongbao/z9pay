<?php

namespace App\Services\Order;

use Throwable;
use Illuminate\Support\Arr;
use App\Models\DepositOrder;
use App\Models\TransferOrder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Common\ReportExceptionService;

class OrderCacheService
{
    private const CACHE_DAYS = 4;
    private const EMPTY_CACHE_SECONDS = 30;

    public function putDeposit(DepositOrder $order, bool $fresh = false): array
    {
        try {
            if ($fresh) {
                $order = $this->freshDeposit($order);
            }
            if (!$order) {
                return [];
            }

            $data = $this->normalizeDeposit($order->toArray());
            $this->putDepositKeys($data);

            return $data;
        } catch (Throwable $e) {
            $this->report('代收订单缓存写入失败', $e, ['order_id' => $order->id ?? null, 'ordernumber' => $order->ordernumber ?? null]);
            return [];
        }
    }

    public function putTransfer(TransferOrder $order, bool $fresh = false): array
    {
        try {
            if ($fresh || !$this->hasRequiredFields($order->getAttributes(), CacheConstPrefixService::CACHE_TRANSFER_FILED)) {
                $order = $this->freshTransfer($order);
            }
            if (!$order) {
                return [];
            }

            $data = $this->normalizeTransfer($order->toArray());
            $this->putTransferKeys($data);

            return $data;
        } catch (Throwable $e) {
            $this->report('代付订单缓存写入失败', $e, ['order_id' => $order->id ?? null, 'ordernumber' => $order->ordernumber ?? null]);
            return [];
        }
    }

    public function getDepositByOrdernumber($ordernumber, bool $cacheOnly = false): array
    {
        $key = CacheConstPrefixService::DEPOSIT_ORDER_INFO . $ordernumber;

        return $this->readDeposit($key, false, $cacheOnly, function () use ($ordernumber) {
            return DepositOrder::query()
                ->where('ordernumber', $ordernumber)
                ->first($this->depositFields());
        }, ['ordernumber' => $ordernumber, 'method' => __FUNCTION__]);
    }

    public function getDepositById($id, bool $cacheOnly = false): array
    {
        $key = CacheConstPrefixService::DEPOSIT_ORDER_ID_INFO . $id;

        return $this->readDeposit($key, false, $cacheOnly, function () use ($id) {
            return DepositOrder::query()
                ->where('id', $id)
                ->first($this->depositFields());
        }, ['order_id' => $id, 'method' => __FUNCTION__]);
    }

    public function getTransferByOrdernumber($ordernumber, bool $cacheOnly = false): array
    {
        $key = CacheConstPrefixService::TRANSFER_ORDER_INFO . $ordernumber;

        return $this->readTransfer($key, false, $cacheOnly, function () use ($ordernumber) {
            return TransferOrder::query()
                ->where('ordernumber', $ordernumber)
                ->first(CacheConstPrefixService::CACHE_TRANSFER_FILED);
        }, ['ordernumber' => $ordernumber, 'method' => __FUNCTION__]);
    }

    public function refreshTransferByOrdernumber($ordernumber): array
    {
        $key = CacheConstPrefixService::TRANSFER_ORDER_INFO . $ordernumber;

        return $this->readTransfer($key, true, false, function () use ($ordernumber) {
            return TransferOrder::query()
                ->where('ordernumber', $ordernumber)
                ->first(CacheConstPrefixService::CACHE_TRANSFER_FILED);
        }, ['ordernumber' => $ordernumber, 'method' => __FUNCTION__]);
    }

    public function getTransferById($id, bool $cacheOnly = false): array
    {
        $key = CacheConstPrefixService::TRANSFER_ORDER_ID_INFO . $id;

        return $this->readTransfer($key, false, $cacheOnly, function () use ($id) {
            return TransferOrder::query()
                ->whereKey($id)
                ->first(CacheConstPrefixService::CACHE_TRANSFER_FILED);
        }, ['order_id' => $id, 'method' => __FUNCTION__]);
    }

    public function getDepositByMerchantOrder($mid, $orderNo, bool $force = false): array
    {
        $key = CacheConstPrefixService::DEPOSIT_ORDER_INFO . $orderNo . '_' . $mid;

        return $this->readDeposit($key, $force, false, function () use ($mid, $orderNo) {
            return DepositOrder::query()
                ->where('mid', $mid)
                ->where('order_no', $orderNo)
                ->first($this->depositFields());
        }, ['mid' => $mid, 'order_no' => $orderNo, 'method' => __FUNCTION__]);
    }

    public function getTransferByMerchantOrder($mid, $orderNo, bool $force = false): array
    {
        $key = CacheConstPrefixService::TRANSFER_ORDER_INFO . $orderNo . '_' . $mid;

        return $this->readTransfer($key, $force, false, function () use ($mid, $orderNo) {
            return TransferOrder::query()
                ->where('mid', $mid)
                ->where('order_no', $orderNo)
                ->first(CacheConstPrefixService::CACHE_TRANSFER_FILED);
        }, ['mid' => $mid, 'order_no' => $orderNo, 'method' => __FUNCTION__]);
    }

    private function readDeposit(string $key, bool $force, bool $cacheOnly, callable $resolver, array $context): array
    {
        try {
            $data = $force ? null : Cache::get($key);
            if ($data !== null) {
                if (empty($data) || $this->hasRequiredFields($data, CacheConstPrefixService::CACHE_DEPOSIT_FIELDS)) {
                    return $this->normalizeDeposit($data);
                }
                Cache::forget($key);
            }

            if ($cacheOnly) {
                return [];
            }

            $model = $resolver();
            if (!$model) {
                Cache::put($key, [], now()->addSeconds(self::EMPTY_CACHE_SECONDS));
                return [];
            }

            return $this->putDeposit($model);
        } catch (Throwable $e) {
            $this->report('代收订单缓存读取失败', $e, $context);
            return [];
        }
    }

    private function readTransfer(string $key, bool $force, bool $cacheOnly, callable $resolver, array $context): array
    {
        try {
            $data = $force ? null : Cache::get($key);
            if ($data !== null) {
                if (empty($data) || $this->hasRequiredFields($data, CacheConstPrefixService::CACHE_TRANSFER_FILED)) {
                    return $this->normalizeTransfer($data);
                }
                Cache::forget($key);
            }

            if ($cacheOnly) {
                return [];
            }

            $model = $resolver();
            if (!$model) {
                Cache::put($key, [], now()->addSeconds(self::EMPTY_CACHE_SECONDS));
                return [];
            }

            return $this->putTransfer($model);
        } catch (Throwable $e) {
            $this->report('代付订单缓存读取失败', $e, $context);
            return [];
        }
    }

    private function putDepositKeys(array $data): void
    {
        foreach ($this->depositKeys($data) as $key) {
            Cache::put($key, $data, now()->addDays(self::CACHE_DAYS));
        }
    }

    private function putTransferKeys(array $data): void
    {
        foreach ($this->transferKeys($data) as $key) {
            Cache::put($key, $data, now()->addDays(self::CACHE_DAYS));
        }
    }

    private function depositKeys(array $data): array
    {
        return array_values(array_filter([
            !empty($data['ordernumber']) ? CacheConstPrefixService::DEPOSIT_ORDER_INFO . $data['ordernumber'] : null,
            !empty($data['order_no']) && isset($data['mid']) ? CacheConstPrefixService::DEPOSIT_ORDER_INFO . $data['order_no'] . '_' . $data['mid'] : null,
            !empty($data['id']) ? CacheConstPrefixService::DEPOSIT_ORDER_ID_INFO . $data['id'] : null,
        ]));
    }

    private function transferKeys(array $data): array
    {
        return array_values(array_filter([
            !empty($data['ordernumber']) ? CacheConstPrefixService::TRANSFER_ORDER_INFO . $data['ordernumber'] : null,
            !empty($data['order_no']) && isset($data['mid']) ? CacheConstPrefixService::TRANSFER_ORDER_INFO . $data['order_no'] . '_' . $data['mid'] : null,
            !empty($data['id']) ? CacheConstPrefixService::TRANSFER_ORDER_ID_INFO . $data['id'] : null,
        ]));
    }

    private function hasRequiredFields($data, array $fields): bool
    {
        if (!is_array($data)) {
            return false;
        }

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                return false;
            }
        }

        return true;
    }

    private function depositFields(): array
    {
        return CacheConstPrefixService::CACHE_DEPOSIT_FIELDS;
    }

    private function normalizeDeposit($data): array
    {
        if (!is_array($data) || empty($data)) {
            return [];
        }

        $depositFields = $this->depositFields();
        $defaults = array_fill_keys($depositFields, null);
        $defaults['actual_amount'] = 0;
        $defaults['merchant_fee'] = 0;
        $defaults['merchant_extra_fee'] = 0;
        $defaults['callback_time'] = 0;
        $defaults['success_time'] = 0;
        $defaults['show_amount'] = 0;

        return array_merge($defaults, Arr::only($data, $depositFields));
    }

    private function freshDeposit(DepositOrder $order): ?DepositOrder
    {
        return DepositOrder::query()
            ->whereKey($order->id)
            ->first($this->depositFields());
    }

    private function normalizeTransfer($data): array
    {
        if (!is_array($data) || empty($data)) {
            return [];
        }

        $defaults = array_fill_keys(CacheConstPrefixService::CACHE_TRANSFER_FILED, null);
        $defaults['actual_amount'] = 0;
        $defaults['merchant_fee'] = 0;
        $defaults['merchant_extra_fee'] = 0;
        $defaults['callback_time'] = 0;
        $defaults['success_time'] = 0;

        return array_merge($defaults, Arr::only($data, CacheConstPrefixService::CACHE_TRANSFER_FILED));
    }

    private function freshTransfer(TransferOrder $order): ?TransferOrder
    {
        return TransferOrder::query()
            ->whereKey($order->id)
            ->first(CacheConstPrefixService::CACHE_TRANSFER_FILED);
    }

    private function report(string $title, Throwable $e, array $context = []): void
    {
        App::make(ReportExceptionService::class)->report($title, $e, $context);
    }
}

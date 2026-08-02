<?php

namespace App\Services\User;

use App\Models\DepositOrder;
use App\Models\TransferOrder;
use App\Models\UserRelation;
use App\Traits\ServiceTraits;
use App\Services\Cache\CacheConstPrefixService;
use Illuminate\Support\Facades\Redis;

class UserMonthTotalAmountService
{
    use ServiceTraits;

    public function excute($user_id = 0, $amount = 0, $is_agent = 0)
    {
        $userId = intval($user_id);
        if ($userId <= 0) {
            return bob_amount_format(0);
        }

        $key = CacheConstPrefixService::USER_MONTH_TOTAL_AMOUNT . date('Y_m') . '_' . $userId;
        if ($amount > 0) {
            // 成功订单只使月累计缓存失效，避免并发时“数据库汇总已包含订单”后又增量一次。
            Redis::del($key);

            return bob_amount_format(0);
        }

        $cachedAmount = Redis::get($key);

        if ($cachedAmount !== null) {
            return bob_amount_format($cachedAmount);
        }

        return $this->update($userId, $key, $is_agent);
    }

    public function update($user_id, $key, $is_agent)
    {
        $beginTimestamp = strtotime(date('Y-m-01') . ' 00:00:00');
        $endTimestamp = strtotime('+1 month', $beginTimestamp);
        $depositQuery = DepositOrder::query()->where('status', 5)->where('success_time', '>=', $beginTimestamp)->where('success_time', '<', $endTimestamp);
        $transferQuery = TransferOrder::query()->where('status', 4)->where('success_time', '>=', $beginTimestamp)->where('success_time', '<', $endTimestamp);

        // 代理统计使用子查询，避免子账号较多时先 pluck 到 PHP 内存。
        if ($is_agent) {
            $depositQuery->whereIn('user_id', UserRelation::query()->select('child_id')->where('parent_id', $user_id));
            $transferQuery->whereIn('user_id', UserRelation::query()->select('child_id')->where('parent_id', $user_id));
        } else {
            $depositQuery->where('user_id', $user_id);
            $transferQuery->where('user_id', $user_id);
        }

        $total = bob_amount_format(floatval($depositQuery->sum('actual_amount')) + floatval($transferQuery->sum('actual_amount')));
        Redis::setex($key, 86400, $total);

        return $total;
    }
}

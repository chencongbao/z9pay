<?php

namespace App\Services\User;

use Exception;
use Throwable;
use App\Models\User;
use App\Traits\ServiceTraits;
use App\Models\UserBalanceLog;
use Illuminate\Support\Facades\DB;
use App\Services\Common\ReportExceptionService;

class UserAgentBalanceChangeService
{
    use ServiceTraits;

    private const TRANSACTION_ATTEMPTS = 3;

    public int $balance_log_id = 0;

    public function excute($data = [])
    {
        try {
            if (!isset($data['user_id']) || !isset($data['amount']) || !isset($data['type'])) {
                throw new Exception("缺少参数");
            }

            if (DB::transactionLevel() > 0) {
                return $this->changeBalanceInTransaction($data);
            }

            return DB::transaction(fn () => $this->changeBalanceInTransaction($data), self::TRANSACTION_ATTEMPTS);
        } catch (Throwable $e) {
            app(ReportExceptionService::class)->report('金主代理余额变化发生异常', $e, [
                'data' => $data,
                'user_id' => $data['user_id'] ?? null,
                'ordernumber' => $data['ordernumber'] ?? null,
            ]);

            throw $e;
        }
    }

    private function changeBalanceInTransaction(array $data): UserBalanceLog
    {
        $user = User::query()->whereKey((int)$data['user_id'])->where('is_agent', 1)->lockForUpdate()->first(['id', 'is_agent', 'balance_amount']);
        if (!$user) {
            throw new Exception("金主代理不存在");
        }

        $type = (int)$data['type'];
        $amount = $this->amountByType($type, (float)$data['amount']);
        if ($amount < 0 && $this->amountLessThan($user->balance_amount, abs($amount))) {
            throw new Exception('金主代理余额不足');
        }

        // 金主代理只有总余额账户，不参与金主佣金、代收、代付子账户规则。
        $user->balance_amount += $amount;
        $user->save();

        $log = UserBalanceLog::create([
            'is_agent' => 1,
            'mid' => isset($data['mid']) ? (int)$data['mid'] : 0,
            'action_user_id' => isset($data['action_user_id']) ? (int)$data['action_user_id'] : 0,
            'amount' => $amount,
            'user_id' => $user->id,
            'user_bank_id' => isset($data['user_bank_id']) ? (int)$data['user_bank_id'] : 0,
            'type' => $type,
            'type_id' => isset($data['type_id']) ? (int)$data['type_id'] : 0,
            'remark' => $data['remark'] ?? '',
            'ordernumber' => $data['ordernumber'] ?? null,
            'order_type' => isset($data['order_type']) ? (int)$data['order_type'] : 0,
            'balance_amount' => $user->balance_amount,
            'type_balance_amount' => 0,
        ]);
        $this->balance_log_id = optional($log)->id ?: 0;

        return $log;
    }

    protected function amountByType(int $type, float $amount): float
    {
        if (in_array($type, [3, 5, 8], true)) {
            return -abs($amount);
        }

        if (in_array($type, [1, 2, 4, 7], true)) {
            return abs($amount);
        }

        return $amount;
    }

    private function amountLessThan($left, $right): bool
    {
        if (function_exists('bccomp')) {
            return bccomp((string)$left, (string)$right, 2) < 0;
        }

        return (float)$left < (float)$right;
    }
}

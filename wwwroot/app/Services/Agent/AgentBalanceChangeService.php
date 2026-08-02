<?php

namespace App\Services\Agent;

use Exception;
use App\Models\AgentUser;
use App\Traits\ServiceTraits;
use App\Models\AgentBalanceLog;
use Illuminate\Support\Facades\DB;
use App\Services\Common\ReportExceptionService;

class AgentBalanceChangeService
{
    use ServiceTraits;

    private const TRANSACTION_ATTEMPTS = 3;

    public $balance_log_id = 0;

    public function excute($data = [])
    {
        try {
            if (!isset($data['agent_id']) || !isset($data['amount']) || !isset($data['type'])) {
                throw new Exception("缺少参数");
            }

            if (DB::transactionLevel() > 0) {
                return $this->changeBalanceInTransaction($data);
            }

            return DB::transaction(fn () => $this->changeBalanceInTransaction($data), self::TRANSACTION_ATTEMPTS);
        } catch (Exception $e) {
            app(ReportExceptionService::class)->report('商户代理余额变化发生异常', $e, [
                'data' => $data,
                'agent_id' => $data['agent_id'] ?? null,
                'ordernumber' => $data['ordernumber'] ?? null,
            ]);

            throw $e;
        }
    }

    private function changeBalanceInTransaction(array $data): AgentBalanceLog
    {
        $model = AgentUser::whereKey($data['agent_id'])->lockForUpdate()->first(['id', 'balance_amount']);
        if (!$model) {
            throw new Exception("商户代理不存在");
        }

        $type = intval($data['type']);
        $amount = $this->amountByType($type, floatval($data['amount']));
        if ($amount < 0 && $this->amountLessThan($model->balance_amount, abs($amount))) {
            throw new Exception('商户代理余额不足');
        }

        $model->balance_amount += $amount;
        $model->save();

        $sdata = [
            'mid' => $data['mid'] ?? 0,
            'action_agent_id' => $data['action_agent_id'] ?? 0,
            'amount' => $amount,
            'agent_id' => $model->id,
            'type' => $type,
            'type_id' => $data['type_id'] ?? 0,
            'remark' => $data['remark'] ?? '',
            'ordernumber' => $data['ordernumber'] ?? null,
        ];
        $sdata['balance_amount'] = $model->balance_amount;
        $log = AgentBalanceLog::create($sdata);
        $this->balance_log_id = optional($log)->id ?: 0;

        return $log;
    }

    protected function amountByType(int $type, float $amount): float
    {
        if (in_array($type, [3, 5, 6, 8], true)) {
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

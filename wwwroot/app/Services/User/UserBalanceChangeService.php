<?php

namespace App\Services\User;

use Throwable;
use App\Models\User;
use App\Traits\ServiceTraits;
use App\Models\UserBalanceLog;
use Illuminate\Support\Facades\DB;
use App\Services\Common\ReportExceptionService;
use App\Jobs\CheckUserDepositAmountNoticeTelegramJob;

class UserBalanceChangeService
{
    use ServiceTraits;

    private const DECREASE_TYPES = [6, 8, 11, 15];
    private const INCREASE_TYPES = [5, 7, 9, 13, 14, 16];
    private const USER_COMMISSION_TYPES = [1, 2, 3, 10, 11, 13, 14, 15];
    private const AGENT_COMMISSION_TYPES = [1, 2, 5, 7, 8];
    private const DEPOSIT_TYPES = [4, 5, 6];
    private const TRANSFER_TYPES = [7, 8, 9, 16];

    public int $balance_log_id = 0;

    public function excute($data = [])
    {
        try {
            if (!isset($data['user_id']) || !isset($data['amount']) || !isset($data['type'])) {
                throw new \Exception("缺少参数");
            }

            $userId = intval($data['user_id']);
            $type = intval($data['type']);
            $amount = $this->amountByType($type, floatval($data['amount']));
            $balanceAccount = (string)($data['balance_account'] ?? '');

            return DB::transaction(function () use ($data, $userId, $type, $amount, $balanceAccount) {
                // 锁定金主账户，保证余额和流水在同一事务内一致。
                $user = User::query()->whereKey($userId)->lockForUpdate()->first(['id', 'is_agent', 'balance_amount', 'deposit_balance_amount', 'transfer_balance_amount', 'commission_balance_amount']);
                if (!$user) {
                    throw new \Exception("金主或代理不存在");
                }

                $balanceAccount = $this->balanceAccount($user, $type, $balanceAccount);
                $accountAmount = $this->accountAmountByType($type, $amount, $balanceAccount);
                $this->ensureSufficientBalance($user, $balanceAccount, $amount, $accountAmount, $type);

                $user->balance_amount += $amount;
                if ($balanceAccount === 'commission') {
                    $user->commission_balance_amount += $accountAmount;
                }

                if (intval($user->is_agent) === 0) {
                    if ($balanceAccount === 'deposit') {
                        $user->deposit_balance_amount += $accountAmount;
                    }
                    if ($balanceAccount === 'transfer') {
                        $user->transfer_balance_amount += $accountAmount;
                    }
                }

                $user->save();

                $type_balance_amount = $this->typeBalanceAmount($user, $balanceAccount);
                $sdata = [
                    'is_agent' => $user->is_agent,
                    'mid' => isset($data['mid']) ? intval($data['mid']) : 0,
                    'action_user_id' => isset($data['action_user_id']) ? intval($data['action_user_id']) : 0,
                    'amount' => $amount,
                    'user_id' => $userId,
                    'user_bank_id' => isset($data['user_bank_id']) ? intval($data['user_bank_id']) : 0,
                    'type' => $type,
                    'type_id' => isset($data['type_id']) ? intval($data['type_id']) : 0,
                    'remark' => isset($data['remark']) ? $data['remark'] : '',
                    'ordernumber' => $data['ordernumber'] ?? null,
                    'order_type' => isset($data['order_type']) ? intval($data['order_type']) : 0,
                    'balance_amount' => $user->balance_amount,
                    'type_balance_amount' => $type_balance_amount,
                ];
                $log = UserBalanceLog::create($sdata);
                $this->balance_log_id = optional($log)->id ?: 0;

                if (intval($user->is_agent) === 0 && in_array($balanceAccount, ['deposit', 'transfer'], true)) {
                    DB::afterCommit(function () use ($user) {
                        dispatch(new CheckUserDepositAmountNoticeTelegramJob($user->id))->onQueue('query');
                    });
                }

                return $log;
            });
        } catch (Throwable $e) {
            app(ReportExceptionService::class)->report('金主或代理余额变化发生异常', $e, [
                'data' => $data,
                'user_id' => $data['user_id'] ?? null,
                'ordernumber' => $data['ordernumber'] ?? null,
            ]);

            throw $e;
        }
    }

    protected function amountByType(int $type, float $amount): float
    {
        if (in_array($type, self::DECREASE_TYPES, true)) {
            return -abs($amount);
        }

        if (in_array($type, self::INCREASE_TYPES, true)) {
            return abs($amount);
        }

        return $amount;
    }

    protected function accountAmountByType(int $type, float $amount, string $balanceAccount): float
    {
        if ($type === 4 && $balanceAccount === 'deposit' && $amount < 0) {
            return abs($amount);
        }

        if ($type === 5 && $balanceAccount === 'deposit') {
            return -abs($amount);
        }

        if ($type === 6 && $balanceAccount === 'deposit') {
            return abs($amount);
        }

        if ($type === 8 && $balanceAccount === 'transfer') {
            return -abs($amount);
        }

        if ($type === 9 && $balanceAccount === 'transfer') {
            return abs($amount);
        }

        return $amount;
    }

    protected function balanceAccount(User $user, int $type, string $balanceAccount = ''): string
    {
        if (in_array($balanceAccount, ['commission', 'deposit', 'transfer'], true)) {
            return $balanceAccount;
        }

        if ($this->isCommissionType($user, $type)) {
            return 'commission';
        }

        if ($this->isDepositType($type)) {
            return 'deposit';
        }

        if ($this->isTransferType($type)) {
            return 'transfer';
        }

        return '';
    }

    protected function isCommissionType(User $user, int $type): bool
    {
        if (intval($user->is_agent) === 1) {
            return in_array($type, self::AGENT_COMMISSION_TYPES, true);
        }

        return in_array($type, self::USER_COMMISSION_TYPES, true);
    }

    protected function isDepositType(int $type): bool
    {
        return in_array($type, self::DEPOSIT_TYPES, true);
    }

    protected function isTransferType(int $type): bool
    {
        return in_array($type, self::TRANSFER_TYPES, true);
    }

    protected function typeBalanceAmount(User $user, string $balanceAccount): float
    {
        if ($balanceAccount === 'commission') {
            return $user->commission_balance_amount;
        }

        if ($balanceAccount === 'deposit') {
            return $user->deposit_balance_amount;
        }

        if ($balanceAccount === 'transfer') {
            return $user->transfer_balance_amount;
        }

        return 0;
    }

    private function ensureSufficientBalance(User $user, string $balanceAccount, float $amount, float $accountAmount, int $type): void
    {
        if ($accountAmount >= 0) {
            return;
        }

        $cost = abs($accountAmount);
        if (!in_array($balanceAccount, ['commission', 'deposit', 'transfer'], true) && $this->amountLessThan($user->balance_amount, $cost)) {
            throw new \Exception('金主或代理余额不足');
        }

        if ($balanceAccount === 'commission' && $this->amountLessThan($user->commission_balance_amount, $cost)) {
            throw new \Exception('佣金余额不足');
        }

        if ($type === 4 && $balanceAccount === 'deposit') {
            return;
        }

        // 金主代收账户、代付账户允许扣成负数，业务侧用流水方向追踪，不在这里拦截。
    }

    private function amountLessThan($left, $right): bool
    {
        if (function_exists('bccomp')) {
            return bccomp((string)$left, (string)$right, 2) < 0;
        }

        return (float)$left < (float)$right;
    }
}

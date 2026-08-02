<?php

namespace App\Services\User;

use App\Models\User;
use App\Traits\ServiceTraits;
use Illuminate\Support\Facades\App;

class GetUserRemainingDepositService
{
    use ServiceTraits;

    private array $items = [];

    public function excute($user_id = 0, $amount = 0, bool $force = false): array
    {
        return $this->handle($user_id, $amount, $force);
    }

    public function handle($user_id, $amount, bool $force = false): array
    {
        $userId = (int)$user_id;
        $amount = bob_amount_format($amount);
        $data = ['status' => 1, 'amount' => $amount];
        if ($userId <= 0) {
            return $data;
        }

        $userData = $this->getUserDepositData($userId, $force);
        if (empty($userData)) {
            return $data;
        }

        $data = array_merge($data, $userData);
        if ((float)$data['deposit_amount'] > 0 && (float)$data['remaining_deposit'] < $amount) {
            $data['status'] = 0;
        }

        return $data;
    }

    public function calculate($depositAmount, $transferBalanceAmount, $depositBalanceAmount, $pendingDepositAmount = 0): array
    {
        $depositAmount = bob_amount_format($depositAmount);
        $transferBalanceAmount = bob_amount_format($transferBalanceAmount);
        $depositBalanceAmount = bob_amount_format($depositBalanceAmount);
        $pendingDepositAmount = bob_amount_format($pendingDepositAmount);

        return [
            'limited' => $depositAmount > 0,
            'deposit_amount' => $depositAmount,
            'transfer_balance_amount' => $transferBalanceAmount,
            'deposit_balance_amount' => $depositBalanceAmount,
            'pending_deposit_amount' => $pendingDepositAmount,
            'total_deposit_amount' => bob_amount_format($depositAmount + $transferBalanceAmount),
            'remaining_deposit' => bob_amount_format($depositAmount + $transferBalanceAmount - $depositBalanceAmount - $pendingDepositAmount),
        ];
    }

    private function getUserDepositData(int $userId, bool $force = false): array
    {
        if (!$force && array_key_exists($userId, $this->items)) {
            return $this->items[$userId];
        }

        $user = User::query()->whereKey($userId)->first(['id', 'deposit_balance_amount', 'deposit_amount', 'transfer_balance_amount']);
        if (!$user) {
            return $this->items[$userId] = [];
        }

        $depositAmount = bob_amount_format($user->deposit_amount);
        $pendingAmount = App::make(GetUserDepositOrderDaifukuanAmountService::class)->excute($userId, $force);

        return $this->items[$userId] = $this->calculate($depositAmount, $user->transfer_balance_amount, $user->deposit_balance_amount, $pendingAmount);
    }
}

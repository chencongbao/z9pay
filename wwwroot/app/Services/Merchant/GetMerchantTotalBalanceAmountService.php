<?php

namespace App\Services\Merchant;

use App\Models\AgentUser;
use App\Models\MerchantInfo;
use App\Traits\ServiceTraits;

class GetMerchantTotalBalanceAmountService
{
    use ServiceTraits;

    public function excute(): float
    {
        $filters = request()->all();
        $merchantInfo = $filters['merchant_info'] ?? [];
        $query = MerchantInfo::query();

        if (is_array($merchantInfo)) {
            if (!empty($merchantInfo['currency_id'])) {
                $query->where('currency_id', $merchantInfo['currency_id']);
            }
            if (!empty($merchantInfo['name'])) {
                $query->where('name', $merchantInfo['name']);
            }
            if (!empty($merchantInfo['coder'])) {
                $query->where('coder', $merchantInfo['coder']);
            }
        }

        if (!empty($filters['id'])) {
            $query->where('merchant_user_id', $filters['id']);
        }

        if (isset($filters['status']) && $filters['status'] >= 0) {
            $query->whereHas('merchant_user', function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            });
        }

        if (!empty($filters['agent_user_id'])) {
            $agentUserId = $filters['agent_user_id'];
            $query->where(function ($query) use ($agentUserId) {
                $query->where('agent_user_id', $agentUserId)->orWhere(function ($query) use ($agentUserId) {
                    $query->whereIn('agent_user_id', AgentUser::query()->where('pid', $agentUserId)->pluck('id'));
                });
            });
        }

        return (float) $query->sum('balance_amount');
    }
}

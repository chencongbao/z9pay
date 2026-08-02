<?php

namespace App\Admin\Forms\User;

use App\Models\User;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\User\TodayDepositOrderTotalAmountService;
use App\Services\User\TodayDepositOrderTotalIncomeService;
use App\Services\User\GetUserRemainingDepositService;
use App\Services\User\UserPendingDepositOrderStatsService;

class CacheStats extends Form implements LazyRenderable
{
    use LazyWidget;

    public function form()
    {
        $this->disableSubmitButton();
        $this->disableResetButton();

        $this->display('user_name', '金主');
        $this->display('total_deposit_amount', '总押金');
        $this->display('remaining_deposit_amount', '剩余押金');
        $this->display('deposit_amount', '保证金');
        $this->display('today_deposit_income', '今日收益');
        $this->display('today_deposit_amount', '今日跑量');
        $this->display('pending_deposit_count', '代收待付款订单数');
        $this->display('pending_deposit_amount', '代收待付款订单总额');
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('users-index');
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $user = User::query()->whereKey($id)->where('is_agent', 0)->first(['id', 'username', 'name', 'deposit_amount']);
        if (!$user) {
            return [
                'user_name' => '金主不存在',
                'total_deposit_amount' => '-',
                'remaining_deposit_amount' => '-',
                'deposit_amount' => '-',
                'today_deposit_income' => '-',
                'today_deposit_amount' => '-',
                'pending_deposit_count' => '-',
                'pending_deposit_amount' => '-',
            ];
        }

        // 弹窗查看押金时强制同步一次待付款订单，避免旧缓存字段导致金额与数量不一致。
        $remainingDeposit = app(GetUserRemainingDepositService::class)->excute($user->id, 0, true);
        $pendingDepositCount = app(UserPendingDepositOrderStatsService::class)->count($user->id);

        return [
            'user_name' => '【' . $user->id . '】【' . $user->username . '】' . $user->name,
            'total_deposit_amount' => $remainingDeposit['limited'] ? bob_unit_format($remainingDeposit['total_deposit_amount']) : '不限制',
            'remaining_deposit_amount' => $remainingDeposit['limited'] ? bob_unit_format($remainingDeposit['remaining_deposit']) : '不限制',
            'deposit_amount' => $user->deposit_amount > 0 ? bob_unit_format($user->deposit_amount) : '不限制',
            'today_deposit_income' => app(TodayDepositOrderTotalIncomeService::class)->excute($user->id),
            'today_deposit_amount' => app(TodayDepositOrderTotalAmountService::class)->excute($user->id),
            'pending_deposit_count' => $pendingDepositCount,
            'pending_deposit_amount' => bob_unit_format($remainingDeposit['pending_deposit_amount']),
        ];
    }
}

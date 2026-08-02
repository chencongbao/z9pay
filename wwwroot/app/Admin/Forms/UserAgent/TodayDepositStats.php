<?php

namespace App\Admin\Forms\UserAgent;

use App\Models\User;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\User\TodayDepositOrderTotalAmountService;
use App\Services\User\TodayDepositOrderTotalIncomeService;
use App\Services\User\TodayDepositOrderTotalNumberService;

class TodayDepositStats extends Form implements LazyRenderable
{
    use LazyWidget;

    public function form()
    {
        $this->disableSubmitButton();
        $this->disableResetButton();

        $this->display('agent_name', '金主代理');
        $this->display('today_deposit_number', '今日代收数量');
        $this->display('today_deposit_income', '今日代收收益');
        $this->display('today_deposit_amount', '今日代收跑量');
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('agents-index');
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $agent = User::query()->whereKey($id)->where('is_agent', 1)->first(['id', 'username', 'name']);
        if (!$agent) {
            return [
                'agent_name' => '金主代理不存在',
                'today_deposit_number' => '-',
                'today_deposit_income' => '-',
                'today_deposit_amount' => '-',
            ];
        }

        return [
            'agent_name' => '【' . $agent->id . '】【' . $agent->username . '】' . $agent->name,
            'today_deposit_number' => app(TodayDepositOrderTotalNumberService::class)->excute($agent->id, 0, 1),
            'today_deposit_income' => app(TodayDepositOrderTotalIncomeService::class)->excute($agent->id, 0, 1),
            'today_deposit_amount' => app(TodayDepositOrderTotalAmountService::class)->excute($agent->id, 0, 1),
        ];
    }
}

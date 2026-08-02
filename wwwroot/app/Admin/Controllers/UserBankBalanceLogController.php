<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use App\Models\DepositOrder;
use App\Models\UserBankBalanceLog;
use Dcat\Admin\Grid\LazyRenderable;

class UserBankBalanceLogController extends LazyRenderable
{
    public function grid(): Grid
    {
        $userBankId = (int) request('user_bank_id');
        $query = UserBankBalanceLog::with([
            'admin' => function ($query) {
                $query->select(['id', 'username']);
            },
        ])->select($this->listColumns())->orderByDesc('id');
        if ($userBankId > 0) {
            $query->where('user_bank_id', $userBankId);
        }

        return Grid::make($query, function (Grid $grid) {
            $grid->column('id')->sortable();
            $grid->column('ordernumber', "交易单号")->display(function () {
                if ($this->type == 1 && $this->type_id > 0) {
                    return optional(DepositOrder::query()->whereKey($this->type_id)->first(['id', 'ordernumber']))->offsetGet('ordernumber');
                }

                return '';
            });
            $grid->column('type', '交易类型')->display(function () {
                return optional(config('default.user_bank_balance'))[$this->type];
            })->dot(bob_colors());
            $grid->column('amount', '交易金额');
            $grid->column("balance_amount", "账户余额");
            $grid->column("created_at", "交易时间");
            $grid->column('remark', '备注');
            $grid->column('admin.username', "操作人");
            $grid->disableActions();
        });
    }

    private function listColumns(): array
    {
        return [
            'id', 'user_bank_id', 'action_admin_id', 'type', 'type_id', 'amount', 'balance_amount', 'created_at', 'remark',
        ];
    }
}

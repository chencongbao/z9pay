<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use App\Models\UserDepositDetail;

class UserDepositDetailController extends Grid\LazyRenderable
{
    public function grid(): Grid
    {
        $userId = intval($this->payload['user_id'] ?? 0);
        $query = UserDepositDetail::query()->select(['id', 'user_id', 'admin_id', 'amount', 'balance_amount', 'remark', 'created_at'])->with(['admin:id,name'])->orderByDesc('id');
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        return Grid::make($query, function (Grid $grid) {
            $grid->column('created_at', '创建时间');
            $grid->column('amount', '操作金额');
            if (!in_array(config('app.name'), ['xinpay', 'lixiangpay'], true)) {
                $grid->column('balance_amount', '账户余额');
            }
            $grid->column('admin.name', '操作人');
            $grid->column('remark', '备注');
            $grid->disableActions();
            $grid->paginate(5);
        });
    }
}

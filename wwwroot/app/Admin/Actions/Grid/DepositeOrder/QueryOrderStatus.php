<?php

namespace App\Admin\Actions\Grid\DepositeOrder;

use Dcat\Admin\Admin;
use App\Models\DepositOrder;
use Dcat\Admin\Grid\RowAction;
use App\Jobs\HandlePendingQueryDepositOrderResultJob;

class QueryOrderStatus extends RowAction
{
    protected $title = '手动查询';

    public function handle()
    {
        if (Admin::user()->cannot('deposit-order-query-status')) {
            return $this->response()->error('无手动查询代收订单状态权限');
        }

        $order = DepositOrder::query()->where('id', $this->getKey())->whereIn('status', [3, 4])->first(['id']);
        if (!$order) {
            return $this->response()->error('非法操作');
        }

        dispatch(new HandlePendingQueryDepositOrderResultJob($order->id))->onQueue('query');
        return $this->response()->success('提交成功');
    }

    public function confirm()
    {
        return ['确定再次查询', '查询并更新订单状态'];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('deposit-order-query-status');
    }
}

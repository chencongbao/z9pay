<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use Dcat\Admin\Admin;
use App\Models\TransferOrder;
use Dcat\Admin\Grid\RowAction;
use Illuminate\Support\Facades\Cache;
use App\Jobs\HandlePendingQueryTransferOrderResultJob;

class QueryOrderStatus extends RowAction
{
    protected $title = '手动查询';

    public function handle()
    {
        $order = TransferOrder::query()->where('id', $this->getKey())->first(['id', 'status']);
        if (!$order) {
            return $this->response()->error('订单不存在');
        }

        if ((int) $order->status !== 2) {
            return $this->response()->error('只有待支付订单可以手动查询');
        }

        // 后台手动查询加短锁，避免连续点击重复塞入查询队列。
        $lockKey = 'admin_transfer_query_status:' . $order->id;
        if (!Cache::add($lockKey, 1, now()->addSeconds(30))) {
            return $this->response()->warning('查询任务已提交，请勿重复点击');
        }

        dispatch(new HandlePendingQueryTransferOrderResultJob($order->id))->onQueue('query');
        return $this->response()->success('提交成功');
    }

    public function confirm()
    {
        return ['确定再次查询', '查询并更新订单状态'];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('transfer-orders');
    }
}

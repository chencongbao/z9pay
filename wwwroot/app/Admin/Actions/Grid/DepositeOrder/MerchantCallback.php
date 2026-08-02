<?php

namespace App\Admin\Actions\Grid\DepositeOrder;

use Dcat\Admin\Admin;
use App\Models\DepositOrder;
use Dcat\Admin\Grid\RowAction;
use Illuminate\Support\Facades\Cache;
use App\Jobs\MerchantDepositCallbackJob;

class MerchantCallback extends RowAction
{
    protected $title = '推送回调';

    public function handle()
    {
        if (Admin::user()->cannot('deposit-order-callback')) {
            return $this->response()->error('无推送代收订单回调权限');
        }

        $order = DepositOrder::query()->find($this->getKey(), ['id', 'notify_url']);
        if (!$order) {
            return $this->response()->error('订单不存在');
        }

        if (empty($order->notify_url)) {
            return $this->response()->error('订单未配置回调地址');
        }

        // 后台手动推送加短锁，避免连续点击重复塞入回调队列。
        $lockKey = 'admin_deposit_callback:' . $order->id;
        if (!Cache::add($lockKey, 1, now()->addSeconds(30))) {
            return $this->response()->warning('回调已安排，请勿重复点击');
        }

        dispatch(new MerchantDepositCallbackJob($order->id, 'callback', true))->onQueue('callback');
        return $this->response()->success('已安排推送回调，队列执行后生成记录')->refresh();
    }

    public function confirm()
    {
        return ['推送回调', '确定重新推送回调？'];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('deposit-order-callback');
    }

    protected function parameters()
    {
        return [];
    }
}

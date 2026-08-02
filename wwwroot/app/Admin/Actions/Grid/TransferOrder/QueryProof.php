<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use Throwable;
use App\Models\TransferOrder;
use Dcat\Admin\Grid\RowAction;
use Illuminate\Support\Facades\Cache;
use App\Services\Const\LogConstService;

class QueryProof extends RowAction
{
    protected $title = '查询凭证';

    public function handle()
    {
        $order = TransferOrder::query()->where('id', $this->getKey())->with(['channel' => function ($query) {
            $query->select('id', 'classname');
        }])->first(['id', 'status', 'channel_id', 'ordernumber']);
        if (!$order) {
            return $this->response()->error('订单不存在');
        }

        if ((int) $order->status !== 4) {
            return $this->response()->error('只有成功订单可以查询凭证');
        }

        if (!$order->channel || empty($order->channel->classname)) {
            return $this->response()->error('订单渠道不存在或未配置通道类名');
        }

        // 查询凭证会请求第三方，加短锁避免后台连续点击重复请求。
        $lockKey = 'admin_transfer_query_proof:' . $order->id;
        if (!Cache::add($lockKey, 1, now()->addSeconds(30))) {
            return $this->response()->warning('查询任务已提交，请勿重复点击');
        }

        $classname = 'Richard\\Payment\\Channel\\' . $order->channel->classname;
        if (!class_exists($classname)) {
            return $this->response()->error('通道类不存在');
        }

        try {
            $payment = new $classname(LogConstService::TRANSFER_ORDER_LOG_PREFIX . $order->id);
            if (!method_exists($payment, 'queryProof')) {
                return $this->response()->error('当前通道不支持凭证查询');
            }

            $result = $payment->queryProof($order->ordernumber, $order->id);
            if ($result) {
                return $this->response()->success('查询成功')->refresh();
            }

            return $this->response()->error('查询失败');
        } catch (Throwable $e) {
            return $this->response()->error('查询凭证失败：' . $e->getMessage());
        }
    }

    public function confirm()
    {
        return ['确定操作', '查询代付订单凭证'];
    }
}

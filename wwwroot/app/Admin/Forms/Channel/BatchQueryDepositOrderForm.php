<?php

namespace App\Admin\Forms\Channel;

use Dcat\Admin\Admin;
use App\Models\DepositOrder;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Jobs\HandlePendingQueryDepositOrderResultJob;

class BatchQueryDepositOrderForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        $beginDate = $input['begin_date'] ?? null;
        $endDate = $input['end_date'] ?? null;
        $channelId = intval($this->payload['id'] ?? 0);

        if ($channelId <= 0) {
            return $this->response()->error('渠道参数错误');
        }

        if (empty($beginDate) || empty($endDate)) {
            return $this->response()->error('开始日期结束日期必填');
        }

        $beginTimestamp = strtotime($beginDate);
        $endTimestamp = strtotime($endDate);
        if ($beginTimestamp === false || $endTimestamp === false) {
            return $this->response()->error('查询日期格式错误');
        }

        if ($beginTimestamp > $endTimestamp) {
            return $this->response()->error('结束日期不能小于开始日期');
        }

        $limit = intval($input['limit'] ?? 500);
        if ($limit <= 0 || $limit > 500) {
            return $this->response()->error('查询条数必须在1到500之间');
        }

        // 多取 1 条判断是否超过表单指定上限，避免大范围查询拖垮后台。
        $orderIds = DepositOrder::query()
            ->where('status', 4)
            ->where('channel_id', $channelId)
            ->where('created_at', '>=', $beginDate)
            ->where('created_at', '<=', $endDate)
            ->orderByDesc('created_at')
            ->limit($limit + 1)
            ->pluck('id');

        if ($orderIds->count() > $limit) {
            return $this->response()->error('查询订单量不能超过' . $limit . '单，请缩短查询时间或调大查询条数');
        }

        if ($orderIds->isEmpty()) {
            return $this->response()->warning('没有符合条件的代收订单');
        }

        $total = $orderIds->count();
        foreach ($orderIds as $orderId) {
            dispatch(new HandlePendingQueryDepositOrderResultJob($orderId))->onQueue('query');
        }

        return $this->response()->success('已成功提交执行，共' . $total . '单')->refresh();
    }

    public function form()
    {
        $this->datetimeRange('begin_date', 'end_date', '查询日期')->required();
        $this->number('limit', '查询条数')->rules(['integer', 'min:1', 'max:500'], ['integer' => '查询条数必须是整数', 'min' => '查询条数不能小于1', 'max' => '查询条数不能超过500'])->default(500)->required()->help('最多500单');
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('channels-index');
    }

    public function default()
    {
        return [
            'begin_date' => date('Y-m-d H:i:00', strtotime('-1 day') - 40 * 60),
            'end_date' => date('Y-m-d H:i:00', strtotime('-1 day') - 10 * 60),
            'limit' => 500,
        ];
    }
}

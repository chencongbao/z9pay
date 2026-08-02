<?php

namespace App\Services\Agent;

use Dcat\Admin\Admin;
use App\Models\AgentUserRelation;
use App\Models\TransferOrder;
use App\Traits\ServiceTraits;

class TransferOrderTotalFeeService
{
    use ServiceTraits;

    public function excute($data = [])
    {
        $request = request();
        $adminId = Admin::user()->id;
        $childIds = AgentUserRelation::where('parent_id', $adminId)->pluck('child_id');
        if ($childIds->isEmpty()) {
            return bob_amount_format(0);
        }

        $model = TransferOrder::query()->whereIn('merchant_agent1_id', $childIds);

        if ($request->input('id')) {
            $model = $model->where('id', $request->input('id'));
        }
        if (!bob_is_empty($request->input('amount'))) {
            $model = $model->where('amount', $request->input('amount'));
        }
        if (!bob_is_empty($request->input('actual_amount'))) {
            $model = $model->where('actual_amount', $request->input('actual_amount'));
        }
        if ($request->input('mid')) {
            $model = $model->where('mid', $request->input('mid'));
        }
        if ($request->input('ordernumber')) {
            $model = $model->where('ordernumber', $request->input('ordernumber'));
        }
        if ($request->input('order_no')) {
            $model = $model->where('order_no', $request->input('order_no'));
        }
        if (!bob_is_empty($request->input('hand_success'))) {
            $model = $model->where('hand_success', $request->input('hand_success'));
        }
        if ($request->input('status')) {
            $model = $model->where('status', $request->input('status'));
        }
        if (isset($data['begin_date'], $data['end_date'])) {
            $model = $model->where('created_at', '>=', $data['begin_date'])->where('created_at', '<=', $data['end_date']);
        }
        if ($request->input('created_at')) {
            $created_at = $request->input('created_at');
            $created_at_begin_date = $created_at['start'] ?? '';
            $created_at_end_date = $created_at['end'] ?? '';
            if ($created_at_begin_date) {
                $model = $model->where('created_at', '>=', $created_at_begin_date);
            }
            if ($created_at_end_date) {
                $model = $model->where('created_at', '<=', $created_at_end_date);
            }
        }
        if ($request->input('success_time')) {
            $success_time = $request->input('success_time');
            $success_time_begin_date = $success_time['start'] ?? '';
            $success_time_end_date = $success_time['end'] ?? '';
            if ($success_time_begin_date) {
                $model = $model->where('success_time', '>=', strtotime($success_time_begin_date));
            }
            if ($success_time_end_date) {
                $model = $model->where('success_time', '<=', strtotime($success_time_end_date));
            }
        }

        // 佣金汇总直接交给数据库聚合，避免大列表 chunk 后再 PHP 循环累加。
        $summary = $model->selectRaw(
            'SUM(CASE WHEN merchant_agent1_id = ? THEN merchant_agent1_commission ELSE 0 END + CASE WHEN merchant_agent2_id = ? THEN merchant_agent2_commission ELSE 0 END + CASE WHEN merchant_agent3_id = ? THEN merchant_agent3_commission ELSE 0 END) as total_amount',
            [$adminId, $adminId, $adminId]
        )->first();

        return bob_amount_format($summary->total_amount ?? 0);
    }
}

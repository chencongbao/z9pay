<?php

namespace App\Services\DepositOrder;

use Throwable;
use RuntimeException;
use App\Models\DepositOrder;
use Illuminate\Database\Eloquent\Model;
use App\Services\Common\SystemLogService;
use App\Services\Common\ReportExceptionService;

class AdminManualSuccessService
{
    public function excute(int $orderId, float $actualAmount, string $remark = '', int $adminId = 0, string $adminName = '', string $confirmedBy = ''): DepositOrder
    {
        if ($orderId <= 0) {
            throw new RuntimeException('订单不存在');
        }
        if ($actualAmount < 0) {
            throw new RuntimeException('实付金额不合法');
        }

        // 统一成功服务负责事务、行锁、余额变更、缓存刷新及回调。
        $order = app(ConfirmPaySuccessService::class)->excute($orderId, $actualAmount, true, $remark, $adminId, 1);

        // 核心事务已经提交，操作日志失败不能改变补单的实际成功结果。
        try {
            $this->writeSystemLog($order, $actualAmount, $remark, $adminId, $adminName, $confirmedBy);
        } catch (Throwable $e) {
            app(ReportExceptionService::class)->report('代收订单手动成功日志写入失败', $e, [
                'order_id' => $order->id,
                'actual_amount' => $actualAmount,
                'admin_id' => $adminId,
                'admin_name' => $adminName,
                'confirmed_by' => $confirmedBy,
            ]);
        }

        return $order;
    }

    protected function writeSystemLog(DepositOrder $order, float $actualAmount, string $remark, int $adminId, string $adminName, string $confirmedBy): void
    {
        app(SystemLogService::class)->logAction(
            actionKey: 'deposit.order.success',
            text: '代收订单手动成功',
            subject: $order,
            properties: [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'ordernumber' => $order->ordernumber,
                'amount' => $order->amount,
                'actual_amount' => $actualAmount,
                'remark' => $remark,
                'apply_admin_id' => $adminId,
                'apply_admin_name' => $adminName,
                'confirmed_by' => $confirmedBy,
            ],
            remark: '代收订单手动成功',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: $this->adminUser($adminId)
        );
    }

    protected function adminUser(int $adminId): ?Model
    {
        $adminModel = config('admin.database.users_model');
        if ($adminId <= 0 || !is_string($adminModel) || !is_a($adminModel, Model::class, true)) {
            return null;
        }

        return $adminModel::find($adminId);
    }
}

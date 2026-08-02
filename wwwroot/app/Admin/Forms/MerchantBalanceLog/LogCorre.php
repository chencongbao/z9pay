<?php

namespace App\Admin\Forms\MerchantBalanceLog;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Models\MerchantBalanceLog;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\Merchant\MerchantBalanceChangeService;

class LogCorre extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $id = (int) ($this->payload['id'] ?? 0);
            $remark = trim((string) ($input['remark'] ?? ''));
            $admin = Admin::user();

            if ($id <= 0) {
                throw new RuntimeException('流水参数错误');
            }

            app(AdminGoogle2faService::class)->verify($input['google_2fa_code'] ?? null);

            DB::transaction(function () use ($id, $remark, $admin) {
                // 锁定原流水，避免并发重复冲正。
                $log = MerchantBalanceLog::query()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first(['id', 'mid', 'amount', 'fee', 'type', 'is_corre', 'remark', 'payment_id', 'ordernumber', 'order_no']);

                if (!$log) {
                    throw new RuntimeException('流水不存在');
                }

                if (!in_array((int) $log->type, [11, 12], true)) {
                    throw new RuntimeException('当前流水类型不支持冲正');
                }

                if ((int) $log->is_corre === 1) {
                    throw new RuntimeException('当前流水已冲正，请勿重复操作');
                }

                $reverseAmount = -1 * (float) $log->amount;
                $reverseFee = -1 * (float) $log->fee;
                $reverseRemark = '冲正原流水#' . $log->id;
                if ($remark !== '') {
                    $reverseRemark .= '，备注：' . $remark;
                }

                // 创建反向流水，冲回原加减项金额和手续费。
                $merchantBalanceChangeService = App::make(MerchantBalanceChangeService::class);
                $result = $merchantBalanceChangeService->excute([
                    'mid' => $log->mid,
                    'amount' => $reverseAmount,
                    'fee' => $reverseFee,
                    'type' => 14,
                    'admin_id' => $admin->id,
                    'type_id' => $log->id,
                    'remark' => $reverseRemark,
                    'payment_id' => $log->payment_id,
                    'ordernumber' => $log->ordernumber,
                    'order_no' => $log->order_no,
                ]);

                if (empty($result['success'])) {
                    throw new RuntimeException($result['message'] ?? '商户余额冲正失败');
                }

                $correLogId = (int) $merchantBalanceChangeService->merchant_balance_log_id;
                if ($correLogId <= 0) {
                    throw new RuntimeException('冲正流水生成失败');
                }

                $originRemark = trim((string) $log->remark);
                $originAppend = '已冲正[' . now()->toDateTimeString() . ']，对应流水#' . $correLogId;
                if ($remark !== '') {
                    $originAppend .= '，备注：' . $remark;
                }

                // 关联原流水和冲正流水，便于后续追溯。
                $originUpdated = MerchantBalanceLog::query()->whereKey($log->id)->update([
                    'is_corre' => 1,
                    'corre_log_id' => $correLogId,
                    'remark' => $originRemark ? ($originRemark . '；' . $originAppend) : $originAppend,
                ]);

                if ($originUpdated <= 0) {
                    throw new RuntimeException('原流水冲正状态更新失败');
                }

                $correUpdated = MerchantBalanceLog::query()->whereKey($correLogId)->update([
                    'is_corre' => 0,
                    'corre_log_id' => $log->id,
                ]);

                if ($correUpdated <= 0) {
                    throw new RuntimeException('冲正流水关联更新失败');
                }

                app(SystemLogService::class)->logAction(
                    actionKey: 'merchant.balance.log.corre',
                    text: '商户流水冲正',
                    subject: $log,
                    properties: [
                        'merchant_balance_log_id' => $log->id,
                        'corre_log_id' => $correLogId,
                        'mid' => $log->mid,
                        'type' => $log->type,
                        'reverse_type' => 14,
                        'amount' => $log->amount,
                        'fee' => $log->fee,
                        'remark' => $remark,
                    ],
                    remark: '商户流水冲正',
                    logType: 'operation',
                    actionMethod: 'POST',
                    appType: 'admin',
                    user: $admin
                );
            });

            return $this->response()->success('冲正成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-balance-log-corre');
    }

    public function form()
    {
        $this->display('id', '流水ID');
        $this->display('merchant_name', '商户');
        $this->display('type_text', '交易类型');
        $this->display('amount', '交易金额');
        $this->display('fee', '手续费');
        $this->display('remark_old', '原备注');
        $this->textarea('remark', '冲正备注')->rules('required|max:200', [
            'required' => '冲正备注必填',
            'max' => '冲正备注过长',
        ])->required();

        app(AdminGoogle2faService::class)->appendField($this);

        $this->confirm('确认冲正', '确认提交当前商户流水冲正操作？');
    }

    public function default()
    {
        $id = (int) ($this->payload['id'] ?? 0);
        $log = $id > 0 ? MerchantBalanceLog::query()->whereKey($id)->first(['id', 'mid', 'type', 'amount', 'fee', 'remark']) : null;
        $merchant = $log ? MerchantInfo::query()->whereKey($log->mid)->first(['merchant_user_id', 'name', 'currency_id']) : null;
        $types = config('default.merchant_balance_type', []);

        return [
            'id' => optional($log)->id,
            'merchant_name' => optional($merchant)->bname,
            'type_text' => $types[optional($log)->type] ?? '',
            'amount' => optional($log)->amount,
            'fee' => optional($log)->fee,
            'remark_old' => optional($log)->remark,
            'remark' => '',
            'google_2fa_code' => '',
        ];
    }
}

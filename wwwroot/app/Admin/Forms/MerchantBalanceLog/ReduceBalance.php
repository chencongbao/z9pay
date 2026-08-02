<?php

namespace App\Admin\Forms\MerchantBalanceLog;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Form;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\Merchant\MerchantBalanceChangeService;
use App\Jobs\MerchantBalanceJiaJianNoticeTelegramGroupJob;

class ReduceBalance extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $mid = intval($this->payload['mid'] ?? 0);
            $remark = trim((string) ($input['remark'] ?? ''));
            $amount = bob_amount_format($input['amount'] ?? 0);

            if ($amount <= 0) {
                throw new RuntimeException('金额必须大于0');
            }

            app(AdminGoogle2faService::class)->verify($input['google_2fa_code'] ?? null);

            $merchant = $this->getMerchant($mid);
            if (!$merchant) {
                throw new RuntimeException('非法操作');
            }

            $merchantBalanceLogId = DB::transaction(function () use ($merchant, $amount, $remark, $admin) {
                // 减项统一走余额服务，内部会锁商户余额并校验可用余额。
                $merchantBalanceChangeService = App::make(MerchantBalanceChangeService::class);
                $result = $merchantBalanceChangeService->reduceManual($merchant, $amount, $remark, $admin->id);

                if (empty($result['success'])) {
                    throw new RuntimeException($result['message'] ?? '商户余额减项失败');
                }

                $desc = sprintf('手动减项 商户余额 %.2f', $amount);
                app(SystemLogService::class)->logAction(
                    actionKey: 'merchant.balance.reduce',
                    text: '手动减项 商户余额',
                    subject: $merchant,
                    properties: [
                        'merchant_user_id' => $merchant->merchant_user_id,
                        'amount' => $amount,
                        'fee' => 0,
                        'remark' => $remark,
                    ],
                    remark: $desc,
                    logType: 'operation',
                    actionMethod: 'POST',
                    appType: 'admin',
                    user: $admin
                );

                return $merchantBalanceChangeService->merchant_balance_log_id;
            });

            dispatch(new MerchantBalanceJiaJianNoticeTelegramGroupJob($merchant->merchant_user_id, $merchantBalanceLogId, '【#' . $admin->id . '】' . $admin->name))->onQueue('query');

            return $this->response()->success('操作成功.')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-balance-log-reduce');
    }

    public function form()
    {
        $this->display('name', '商户');
        $this->text('amount', '减项金额')->rules(['numeric', 'between:0,9999999999999999', new DecimalTwoPlaces()], ['numeric' => '减项金额不合法', 'between' => '减项金额不合法'])->required();
        $this->textarea('remark', '备注')->rules('required|max:200', ['required' => '备注必填', 'max' => '备注过长'])->required();
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $mid = intval($this->payload['mid'] ?? 0);
        $merchant = $this->getMerchant($mid);

        return [
            'name' => optional($merchant)->name,
            'amount' => '',
            'remark' => '',
        ];
    }

    private function getMerchant(int $mid): ?MerchantInfo
    {
        if ($mid <= 0) {
            return null;
        }

        return MerchantInfo::query()->whereKey($mid)->first(['merchant_user_id', 'name']);
    }
}

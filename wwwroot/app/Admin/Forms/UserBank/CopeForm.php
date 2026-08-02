<?php

namespace App\Admin\Forms\UserBank;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\UserBank;
use Dcat\Admin\Widgets\Form;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;

class CopeForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            if (Admin::user()->cannot('user-bank-copy')) {
                throw new \Exception('无复制金主收款卡权限');
            }

            $id = $this->payload['id'] ?? 0;
            $paymentId = $input['payment_id'] ?? 0;
            $limintMinAmount = $input['limint_min_amount'] ?? 0;
            $limintMaxAmount = $input['limint_max_amount'] ?? 0;
            $collectionStatus = $input['collection_status'] ?? 0;

            // 复制收款卡，只覆盖表单指定的通道、状态和限额字段。
            [$model, $copy] = DB::transaction(function () use ($id, $paymentId, $limintMinAmount, $limintMaxAmount, $collectionStatus) {
                $model = UserBank::query()->whereKey($id)->lockForUpdate()->first();
                if (!$model) {
                    throw new \Exception('收款卡不存在');
                }

                $this->checkDuplicate($model, (int)$paymentId);

                $copy = $model->replicate();
                $copy->payment_id = $paymentId;
                $copy->limint_min_amount = $limintMinAmount;
                $copy->limint_max_amount = $limintMaxAmount;
                $copy->collection_status = $collectionStatus;
                $this->resetRuntimeFields($copy);
                $copy->save();

                return [$model, $copy];
            });

            app(SystemLogService::class)->logAction(
                actionKey: 'userbank.copy',
                text: '复制 收款卡',
                subject: $model,
                properties: [
                    'user_bank_id' => $model->id,
                    'new_user_bank_id' => $copy->id,
                    'payment_id' => $paymentId,
                    'collection_status' => $collectionStatus,
                    'limint_min_amount' => $limintMinAmount,
                    'limint_max_amount' => $limintMaxAmount,
                ],
                remark: '复制 收款卡',
                logType: 'operation',
                actionMethod: 'POST',
                appType: 'admin',
                user: Admin::user()
            );

            return $this->response()->success('复制成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-copy');
    }

    public function form()
    {
        $this->confirm('确认复制', '复制作为新的一条插入');
        $this->select('payment_id', '通道类型')->options(collect(config('payment'))->pluck('name', 'id'))->default(1)->disableClearButton()->required();
        $this->number('limint_min_amount', '单笔最低限额')->rules(['numeric', 'between:0,9999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '数值不合法'])->default(0)->required()->help('0为不限制');
        $this->number('limint_max_amount', '单笔最高限额')->rules(['numeric', 'between:0,9999999', new DecimalTwoPlaces(), 'gte:limint_min_amount'], ['numeric' => '数值不合法', 'between' => '数值不合法', 'gte' => '单笔最高限额必须大于等于单笔最低限额'])->default(0)->required()->help('0为不限制');
        $this->radio('collection_status', '收款状态')->options([0 => '收单停止', 1 => '收单启动']);
    }

    public function default()
    {
        $id = $this->payload['id'] ?? 0;
        $model = UserBank::query()->find($id);
        if (!$model) {
            return [
                'payment_id' => 1,
                'collection_status' => 1,
                'limint_min_amount' => 0,
                'limint_max_amount' => 0,
            ];
        }

        return [
            'payment_id' => $model->payment_id,
            'collection_status' => $model->collection_status,
            'limint_min_amount' => $model->limint_min_amount,
            'limint_max_amount' => $model->limint_max_amount,
        ];
    }

    private function checkDuplicate(UserBank $model, int $paymentId): void
    {
        $cardNo = trim((string)$model->card_no);
        if ($cardNo === '') {
            return;
        }

        $exists = UserBank::query()
            ->where('card_no', $cardNo)
            ->lockForUpdate()
            ->get(['id', 'card_no', 'payment_id'])
            ->contains('payment_id', $paymentId);

        if ($exists) {
            throw new \Exception('复制失败：收款账号【' . $cardNo . '】在目标通道下已存在');
        }
    }

    private function resetRuntimeFields(UserBank $copy): void
    {
        $copy->balance_amount = 0;
        $copy->doing_status = 0;
        $copy->last_collection_time = null;
        $copy->today_stat_date = null;
        $copy->today_total_amount = 0;
        $copy->today_total_number = 0;
        $copy->today_total_income = 0;
    }
}

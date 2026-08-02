<?php

namespace App\Admin\Forms\UserBank;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\UserBank;
use Dcat\Admin\Widgets\Form;
use App\Rules\DecimalTwoPlaces;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;

class BatchUpdateLimitMinMaxAmountForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            if (Admin::user()->cannot('user-bank-batch-limit')) {
                throw new \Exception('无批量修改金主收款卡限额权限');
            }

            $keys = $this->parseIds($input['id'] ?? '');
            if (empty($keys)) {
                throw new \Exception('请选择数据项');
            }

            $limintMinAmount = $input['limint_min_amount'] ?? 0;
            $limintMaxAmount = $input['limint_max_amount'] ?? 0;

            // 批量更新单笔限额，使用一次 SQL 减少逐条保存开销。
            $affected = UserBank::query()->whereKey($keys)->update([
                'limint_min_amount' => $limintMinAmount,
                'limint_max_amount' => $limintMaxAmount,
            ]);

            app(SystemLogService::class)->logAction(
                actionKey: 'userbank.batch.update_limit',
                text: '批量修改 收款卡单笔限额',
                subject: null,
                properties: [
                    'ids' => $keys,
                    'affected' => $affected,
                    'limint_min_amount' => $limintMinAmount,
                    'limint_max_amount' => $limintMaxAmount,
                ],
                remark: '批量修改 收款卡单笔限额',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: Admin::user()
            );

            return $this->response()->success('修改成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-batch-limit');
    }

    public function form()
    {
        $this->number('limint_min_amount')->rules(['numeric', 'between:0,9999999', new DecimalTwoPlaces()], ['numeric' => '数值不合法', 'between' => '数值不合法'])->default(0)->required();
        $this->number('limint_max_amount')->rules(['numeric', 'between:0,9999999', new DecimalTwoPlaces(), 'gte:limint_min_amount'], ['numeric' => '数值不合法', 'between' => '数值不合法', 'gte' => '单笔最高限额必须大于等于单笔最低限额'])->default(0)->required();
        $this->hidden('id')->attribute('id', 'user-bank-id');
    }

    public function default()
    {
        return [
            'limint_min_amount' => 0,
            'limint_max_amount' => 0,
        ];
    }

    public function actionScript()
    {
        $warning = __('请选择操作项!');

        return <<<JS
function (data, target, action) {
    var key = {$this->getSelectedKeysScript()}

    if (key.length === 0) {
        Dcat.error('{$warning}');
        return false;
    }

    action.options.key = key;
}
JS;
    }

    private function parseIds($ids): array
    {
        $ids = is_array($ids) ? $ids : explode(',', (string)$ids);

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}

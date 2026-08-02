<?php

namespace App\Admin\Actions\Grid\UserBank;

use Dcat\Admin\Admin;
use App\Models\UserBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Grid\BatchAction;
use App\Services\Common\SystemLogService;
use App\Services\Cache\UserBank\GetUserBankListService;
use App\Services\Cache\UserBank\GetUserBankDetailService;

class BatchOpenUserBank extends BatchAction
{
    protected $title = '<button class="btn btn-primary">一键开启</button>';

    public function handle(Request $request)
    {
        if (Admin::user()->cannot('user-bank-batch-open')) {
            return $this->response()->error('无批量开启金主收款卡权限');
        }

        $ids = $this->selectedIds();
        if (empty($ids)) {
            return $this->response()->error('请选择操作项');
        }

        $affected = 0;
        DB::transaction(function () use ($ids, &$affected) {
            $affected = UserBank::query()->whereKey($ids)->update(['collection_status' => 1]);
        });
        $this->refreshUserBankCaches($ids);

        app(SystemLogService::class)->logAction(
            actionKey: 'userbank.batch.open',
            text: '批量开启 收款卡',
            subject: null,
            properties: [
                'ids' => $ids,
                'affected' => $affected,
            ],
            remark: '批量开启 收款卡',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );

        return $this->response()->success('操作成功')->refresh();
    }

    public function confirm()
    {
        return ['确定操作?', '开启所有收款卡'];
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

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-batch-open');
    }

    private function selectedIds(): array
    {
        $keys = array_values(array_filter(array_map('intval', (array)$this->getKey()), fn (int $id) => $id > 0));
        if (empty($keys)) {
            return [];
        }

        return UserBank::query()->whereKey($keys)->pluck('id')->map(fn ($id) => (int)$id)->all();
    }

    private function refreshUserBankCaches(array $ids): void
    {
        foreach ($ids as $id) {
            app(GetUserBankDetailService::class)->excute($id, true);
        }

        app(GetUserBankListService::class)->excute(true);
    }
}

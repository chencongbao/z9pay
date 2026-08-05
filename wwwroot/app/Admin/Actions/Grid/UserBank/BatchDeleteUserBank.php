<?php

namespace App\Admin\Actions\Grid\UserBank;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\UserBank;
use Illuminate\Http\Request;
use Dcat\Admin\Grid\BatchAction;
use Illuminate\Support\Facades\DB;
use App\Services\Common\SystemLogService;
use App\Services\Cache\UserBank\GetUserBankListService;
use App\Services\Cache\UserBank\GetUserBankDetailService;

class BatchDeleteUserBank extends BatchAction
{
    protected $title = '<button class="btn btn-danger user-bank-batch-delete-btn">批量删除</button>';

    public function handle(Request $request)
    {
        try {
            $admin = Admin::user();
            if ($admin->cannot('user-bank-delete')) {
                return $this->response()->error('无删除金主收款卡权限');
            }

            $ids = $this->selectedIds();
            if (empty($ids)) {
                return $this->response()->error('请选择操作项');
            }

            $deletedCount = DB::transaction(function () use ($ids) {
                $userBanks = UserBank::query()->whereKey($ids)->lockForUpdate()->get(['id']);
                if ($userBanks->count() !== count($ids)) {
                    throw new \Exception('部分收款卡不存在，请刷新后重试');
                }

                return UserBank::query()->whereKey($ids)->update(['deleted_at' => now(), 'updated_at' => now()]);
            });

            foreach ($ids as $id) {
                app(GetUserBankDetailService::class)->excute($id, true);
            }
            app(GetUserBankListService::class)->excute(true);

            app(SystemLogService::class)->logAction(
                actionKey: 'userbank.batch.delete',
                text: '批量删除 收款卡',
                subject: null,
                properties: ['ids' => $ids, 'deleted_count' => $deletedCount],
                remark: '批量删除 收款卡',
                logType: 'operation',
                actionMethod: 'DELETE',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('批量删除成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    public function confirm()
    {
        return ['确认批量删除', '删除后可在回收站还原'];
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
        return Admin::user()->can('user-bank-delete');
    }

    private function selectedIds(): array
    {
        $keys = $this->getKey();
        $keys = is_string($keys) ? explode(',', $keys) : (array)$keys;
        $result = [];
        foreach ($keys as $id) {
            if ((!is_int($id) && !is_string($id)) || !ctype_digit((string)$id) || (int)$id <= 0) {
                throw new \Exception('收款卡编号不合法');
            }

            $result[] = (int)$id;
        }

        $result = array_values(array_unique($result));
        if (count($result) > 1000) {
            throw new \Exception('单次最多批量删除1000张收款卡');
        }

        return $result;
    }
}

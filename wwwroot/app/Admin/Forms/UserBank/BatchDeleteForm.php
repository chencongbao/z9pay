<?php

namespace App\Admin\Forms\UserBank;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\UserBank;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\Cache\UserBank\GetUserBankListService;

class BatchDeleteForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            if ($admin->cannot('user-bank-delete')) {
                throw new \Exception('无删除金主收款卡权限');
            }

            $ids = $this->parseIds($input['id'] ?? '');
            if (empty($ids)) {
                throw new \Exception('请选择操作项');
            }

            if (!Hash::check((string)($input['password'] ?? ''), $admin->password)) {
                throw new \Exception('操作人登录密码错误');
            }

            app(AdminGoogle2faService::class)->verify($input['google_2fa_code'] ?? '');

            $deletedCount = DB::transaction(function () use ($ids) {
                $userBanks = UserBank::query()->whereKey($ids)->lockForUpdate()->get(['id']);
                if ($userBanks->count() !== count($ids)) {
                    throw new \Exception('部分收款卡不存在，请刷新后重试');
                }

                return UserBank::query()->whereKey($ids)->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
            });

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

    public function form()
    {
        $this->confirm('确认批量删除', '删除后可在回收站还原');
        $this->hidden('id')->attribute('id', 'user-bank-batch-delete-ids');
        $this->password('password', '操作人登录密码')->required()->help('用于确认本次敏感操作，不是被操作账号的密码');
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        return ['password' => '', 'google_2fa_code' => ''];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-delete');
    }

    private function parseIds($ids): array
    {
        if ($ids === '' || $ids === null || $ids === []) {
            return [];
        }

        $ids = is_array($ids) ? $ids : explode(',', (string)$ids);
        $result = [];
        foreach ($ids as $id) {
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

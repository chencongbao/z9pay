<?php

namespace App\MerchantAdmin\Actions\User;

use Dcat\Admin\Admin;
use App\Models\MerchantUser;
use Illuminate\Http\Request;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Actions\Response;
use App\Services\Common\SystemLogService;

class Delete extends RowAction
{
    public function title(): string
    {
        return '<i class="feather icon-trash-2"></i> ' . __('admin.delete');
    }

    public function handle(Request $request): Response
    {
        $admin = Admin::user();
        $id = $this->targetId($this->getKey());

        // 只允许商户主账号删除自己名下的子账号。
        if ((int)optional($admin)->pid > 0) {
            return $this->response()->error(admin_trans_field('illegal_operation'));
        }
        if ($id <= 0) {
            return $this->response()->error(admin_trans_field('account_params_error'));
        }

        $user = MerchantUser::query()
            ->whereKey($id)
            ->where('pid', bob_merchant_user_pid())
            ->first(['id', 'username', 'name', 'pid']);

        if (!$user) {
            return $this->response()->error(admin_trans_field('account_not_found_or_forbidden'));
        }

        // 删除成功后写商户端操作日志，保留被删除子账号信息。
        $user->delete();
        $this->writeLog($user, $admin);

        return $this->response()->success(__('admin.delete_succeeded'))->refresh();
    }

    public function confirm(): array
    {
        return [__('admin.delete_confirm')];
    }

    private function writeLog(MerchantUser $user, $admin): void
    {
        app(SystemLogService::class)->logAction(
            actionKey: 'merchant.user.delete',
            text: '删除 商户子账号',
            subject: $user,
            properties: [
                'merchant_user_id' => $user->id,
                'pid' => $user->pid,
                'username' => (string)$user->username,
                'name' => (string)$user->name,
            ],
            remark: '删除 商户子账号（账号:' . (string)$user->username . '，名称:' . (string)$user->name . '）',
            logType: 'operation',
            actionMethod: 'DELETE',
            appType: 'merchant',
            user: $admin
        );
    }

    private function targetId(mixed $id): int
    {
        if (is_int($id)) {
            return $id > 0 ? $id : 0;
        }

        if (is_string($id) && preg_match('/^[1-9]\d*$/', $id)) {
            return (int)$id;
        }

        return 0;
    }
}

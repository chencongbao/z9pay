<?php

namespace App\MerchantAdmin\Actions\User;

use Dcat\Admin\Admin;
use App\Models\MerchantUser;
use Illuminate\Http\Request;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Actions\Response;
use App\Services\Common\SystemLogService;
use App\Services\Throttles\MerchantUserLoginThrottleService;

class UnlockLogin extends RowAction
{
    public function title(): string
    {
        return '<i class="feather icon-power"></i> ' . __('admin.unlock_login');
    }

    public function handle(Request $request): Response
    {
        $admin = Admin::user();
        $id = $this->targetId($this->getKey());

        // 只允许商户主账号解锁自己名下的子账号。
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

        app(MerchantUserLoginThrottleService::class)->unlockByUsername((string)$user->username);
        $this->writeLog($user, $admin);

        return $this->response()->success(__('admin.unlock_succeeded'))->refresh();
    }

    public function confirm(): array
    {
        return [__('admin.unlock_confirm'), __('admin.unlock_login')];
    }

    private function writeLog(MerchantUser $user, $admin): void
    {
        app(SystemLogService::class)->logAction(
            actionKey: 'merchant.user.unlock',
            text: '解锁 商户子账号登录',
            subject: $user,
            properties: [
                'merchant_user_id' => $user->id,
                'pid' => $user->pid,
                'username' => (string)$user->username,
                'name' => (string)$user->name,
            ],
            remark: '解锁 商户子账号登录（账号:' . (string)$user->username . '，名称:' . (string)$user->name . '）',
            logType: 'operation',
            actionMethod: 'PUT',
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

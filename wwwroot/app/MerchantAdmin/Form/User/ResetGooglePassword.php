<?php

namespace App\MerchantAdmin\Form\User;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\MerchantUser;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Hash;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;

class ResetGooglePassword extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $id = $this->targetId($this->payload['id'] ?? null);
            $password = (string)($input['password'] ?? '');
            $google2faCode = (string)($input['google_2fa_code'] ?? '');

            // 校验操作人身份和目标子账号归属，避免子账号或跨商户操作。
            if ((int)optional($admin)->pid > 0) {
                throw new RuntimeException(admin_trans_field('illegal_operation'));
            }
            if ($id <= 0) {
                throw new RuntimeException(admin_trans_field('account_params_error'));
            }

            $user = $this->targetUser($id);
            if (!$user) {
                throw new RuntimeException(admin_trans_field('account_not_found_or_forbidden'));
            }
            if (!Hash::check($password, $admin->password)) {
                throw new RuntimeException(admin_trans_label('login_password_error'));
            }

            // 重置子账号谷歌验证，并强制清空会话让其重新登录绑定。
            app(AdminGoogle2faService::class)->verify($google2faCode);
            $this->resetGoogle($user);
            $this->writeLog($user, $admin);

            return $this->response()->success(admin_trans_label('reset_success'))->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    public function form()
    {
        $this->display('name', __('admin.name'));
        $this->password('password', admin_trans_field('operator_login_password'))->required()->help(admin_trans_label('sensitive_operation_password_help'));
        app(AdminGoogle2faService::class)->appendField($this);
    }

    protected function authorize($user): bool
    {
        return (int)optional(Admin::user())->pid === 0;
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $user = $id > 0 ? $this->targetUser($id) : null;

        return [
            'name' => optional($user)->name,
            'password' => '',
            'google_2fa_code' => '',
        ];
    }

    private function targetUser(int $id): ?MerchantUser
    {
        return MerchantUser::query()
            ->where('pid', bob_merchant_user_pid())
            ->whereKey($id)
            ->first(['id', 'name', 'pid', 'google_two_fa_secret', 'google_two_fa_bind', 'google_two_fa_enable', 'session_id']);
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

    private function resetGoogle(MerchantUser $user): void
    {
        $user->forceFill([
            'google_two_fa_secret' => (new Google2FA())->generateSecretKey(32),
            'google_two_fa_bind' => 0,
            'google_two_fa_enable' => 1,
            'session_id' => '',
        ])->save();
    }

    private function writeLog(MerchantUser $user, $admin): void
    {
        // 记录敏感操作日志，方便商户后台追踪操作人。
        app(SystemLogService::class)->logAction(
            actionKey: 'merchant.user.reset_google',
            text: '重置 谷歌验证码',
            subject: $user,
            properties: [
                'merchant_user_id' => $user->id,
                'google_two_fa_bind' => 0,
            ],
            remark: '重置 谷歌验证码',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'merchant',
            user: $admin
        );
    }
}

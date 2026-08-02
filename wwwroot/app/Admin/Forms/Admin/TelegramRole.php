<?php

namespace App\Admin\Forms\Admin;

use Dcat\Admin\Admin;
use App\Models\AdminUser;
use Dcat\Admin\Widgets\Form;
use App\Models\AdminAdministrator;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\Hash;
use App\Services\Common\SystemLogService;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Google\AdminGoogle2faService;

class TelegramRole extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $id = intval($this->payload['id'] ?? 0);
            $telegramUserId = intval($input['telegram_user_id'] ?? 0);
            $telegramRole = intval($input['telegram_role'] ?? AdminAdministrator::TELEGRAM_ROLE_NONE);
            $password = (string)($input['password'] ?? '');
            $google2faCode = (string)($input['google_2fa_code'] ?? '');

            $user = AdminUser::query()->find($id, ['id', 'username', 'name', 'telegram_user_id', 'telegram_role']);
            if (!$user) {
                throw new \Exception('管理员不存在');
            }
            if (!Hash::check($password, Admin::user()->password)) {
                throw new \Exception('操作人登录密码错误');
            }
            if ($telegramRole > AdminAdministrator::TELEGRAM_ROLE_NONE && $telegramUserId <= 0) {
                throw new \Exception('设置飞机权限时，Telegram用户ID必须大于0');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);

            $oldData = [
                'telegram_user_id' => intval($user->telegram_user_id),
                'telegram_role' => intval($user->telegram_role),
            ];

            $user->forceFill([
                'telegram_user_id' => $telegramUserId,
                'telegram_role' => $telegramRole,
            ])->save();

            app(SystemLogService::class)->logAction(
                actionKey: 'admin.user.telegram_role.update',
                text: '设置 管理员飞机权限',
                subject: $user,
                properties: [
                    'admin_user_id' => $user->id,
                    'username' => $user->username,
                    'old' => $oldData,
                    'new' => [
                        'telegram_user_id' => $telegramUserId,
                        'telegram_role' => $telegramRole,
                    ],
                ],
                remark: '设置 管理员飞机权限',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: Admin::user()
            );

            return $this->response()->success('设置成功')->refresh();
        } catch (\Exception $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('admin-user-telegram-role');
    }

    public function form()
    {
        $this->display('name', '管理员姓名');
        $this->display('username', '管理员用户名');
        $this->text('telegram_user_id', 'Telegram用户ID')->default('0')->help('可在飞机群里发送“个人信息”获取个人ID');
        $this->radio('telegram_role', '飞机权限')->options($this->telegramRoleOptions())->default(AdminAdministrator::TELEGRAM_ROLE_NONE)->required();
        $this->password('password', '操作人登录密码')->required()->help('用于确认本次敏感操作，不是被操作账号的密码');
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = intval($this->payload['id'] ?? 0);
        $user = AdminUser::query()->find($id, ['id', 'name', 'username', 'telegram_user_id', 'telegram_role']);

        return [
            'name' => optional($user)->name,
            'username' => optional($user)->username,
            'telegram_user_id' => (string)intval(optional($user)->telegram_user_id),
            'telegram_role' => intval(optional($user)->telegram_role),
            'password' => '',
            'google_2fa_code' => '',
        ];
    }

    private function telegramRoleOptions(): array
    {
        return [
            AdminAdministrator::TELEGRAM_ROLE_NONE => '无',
            AdminAdministrator::TELEGRAM_ROLE_MANAGER => '飞机命令管理员',
            AdminAdministrator::TELEGRAM_ROLE_SUPER_MANAGER => '飞机命令超级管理员',
        ];
    }
}

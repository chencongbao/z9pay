<?php

namespace App\Admin\Forms\User;

use Throwable;
use App\Models\User;
use Dcat\Admin\Admin;
use RuntimeException;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
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
            $id = (int)($this->payload['id'] ?? 0);
            $googleTwoFaEnable = (int)($input['google_two_fa_enable'] ?? 0);
            $resetGoogle = (int)($input['reset_google'] ?? 0);
            $google2faCode = (string)($input['google_2fa_code'] ?? '');

            if ($id <= 0) {
                throw new RuntimeException('金主参数错误');
            }
            if (!in_array($googleTwoFaEnable, [1, 2], true)) {
                throw new RuntimeException('谷歌验证码开关参数错误');
            }
            if (!in_array($resetGoogle, [0, 1], true)) {
                throw new RuntimeException('重置谷歌验证参数错误');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);

            $user = User::query()->whereKey($id)->first(['id', 'name', 'google_two_fa_secret', 'google_two_fa_enable']);
            if (!$user) {
                throw new RuntimeException('金主不存在');
            }

            $oldGoogleTwoFaEnable = (int)$user->google_two_fa_enable;
            $updateData = ['google_two_fa_enable' => $googleTwoFaEnable];
            $resetGoogleSecret = $googleTwoFaEnable === 1 || $resetGoogle === 1;
            if ($resetGoogleSecret) {
                $updateData['google_two_fa_secret'] = '';
            }

            $user->forceFill($updateData)->save();

            app(SystemLogService::class)->logAction(
                actionKey: 'user.reset_google',
                text: '重置 金主(代理)谷歌验证码',
                subject: $user,
                properties: [
                    'user_id' => $user->id,
                    'old_google_two_fa_enable' => $oldGoogleTwoFaEnable,
                    'google_two_fa_enable' => $googleTwoFaEnable,
                    'reset_google' => $resetGoogleSecret ? 1 : 0,
                ],
                remark: '重置 金主(代理)谷歌验证码',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('操作成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-reset-googlecode');
    }

    public function form()
    {
        $this->display('name', '金主');
        $this->radio('google_two_fa_enable', '开关验证码')->options([1 => '关闭【通用验证码登录:000000】', 2 => '开启【谷歌验证码登录】'])->when(2, function () {
            $this->radio('reset_google', '重置谷歌验证')->options([0 => '否', 1 => '是'])->default(0);
        })->default(1);
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = (int)($this->payload['id'] ?? 0);
        $user = User::query()->whereKey($id)->first(['id', 'username', 'name', 'google_two_fa_enable']);

        return [
            'name' => optional($user)->name,
            'google_two_fa_enable' => optional($user)->google_two_fa_enable ?: 1,
            'reset_google' => 0,
            'google_2fa_code' => '',
        ];
    }
}

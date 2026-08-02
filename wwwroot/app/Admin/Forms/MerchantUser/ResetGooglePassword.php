<?php

namespace App\Admin\Forms\MerchantUser;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use Dcat\Admin\Widgets\Form;
use App\Models\MerchantUser;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\Hash;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Google\AdminGoogle2faService;
use App\Services\Google\AdminResetGoogleService;

class ResetGooglePassword extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $adminUser = Admin::user();
            if ($adminUser->cannot('merchant-user-reset-googlecode')) {
                throw new RuntimeException('非法操作');
            }

            $id = intval($this->payload['id'] ?? 0);
            $password = (string)($input['password'] ?? '');
            $google2faCode = (string)($input['google_2fa_code'] ?? '');

            if ($id <= 0) {
                throw new RuntimeException('商户参数错误');
            }

            $user = MerchantUser::query()
                ->with(['merchant_info' => function ($query) {
                    $query->select(['merchant_user_id', 'name', 'coder']);
                }])
                ->whereKey($id)
                ->first(['id']);

            if (!$user) {
                throw new RuntimeException('商户不存在');
            }
            if (!Hash::check($password, $adminUser->password)) {
                throw new RuntimeException('操作人登录密码错误');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);
            app(AdminResetGoogleService::class)->resetMerchant($user);

            return $this->response()->success('重置成功，请让商户退出账号，重新登录')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-user-reset-googlecode');
    }

    public function form()
    {
        $this->display('name', '商户名称');
        $this->password('password', '操作人登录密码')->required()->help('用于确认本次敏感操作，不是被操作账号的密码');
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = intval($this->payload['id'] ?? 0);
        if ($id <= 0) {
            return [
                'name' => '',
                'password' => '',
                'google_2fa_code' => '',
            ];
        }

        $user = MerchantUser::query()
            ->with(['merchant_info' => function ($query) {
                $query->select(['merchant_user_id', 'name']);
            }])
            ->whereKey($id)
            ->first(['id']);

        return [
            'name' => optional(optional($user)->merchant_info)->name,
            'password' => '',
            'google_2fa_code' => '',
        ];
    }
}

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

class Delete extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            if ($admin->cannot('user-bank-delete')) {
                throw new \Exception('无删除金主收款卡权限');
            }

            $id = $this->payload['id'] ?? 0;
            $password = $input['password'] ?? '';
            $google2faCode = $input['google_2fa_code'] ?? '';
            if (!Hash::check($password, $admin->password)) {
                throw new \Exception('操作人登录密码错误');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);

            // 锁定收款卡后删除，避免并发重复操作。
            $result = DB::transaction(function () use ($id) {
                $result = UserBank::query()->whereKey($id)->lockForUpdate()->first();
                if (!$result) {
                    throw new \Exception('收款卡不存在');
                }

                $result->delete();

                return $result;
            });

            app(SystemLogService::class)->logAction(
                actionKey: 'userbank.delete',
                text: '删除 收款卡',
                subject: $result,
                properties: [
                    'user_bank_id' => $result->id,
                    'user_id' => $result->user_id,
                ],
                remark: '删除 收款卡',
                logType: 'operation',
                actionMethod: 'DELETE',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('删除成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    public function form()
    {
        $this->confirm('确认删除', '删除后可在回收站还原');
        $this->password('password', '操作人登录密码')->required()->help('用于确认本次敏感操作，不是被操作账号的密码');
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        return [
            'password' => '',
            'google_2fa_code' => '',
        ];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-delete');
    }
}

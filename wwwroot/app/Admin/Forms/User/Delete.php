<?php

namespace App\Admin\Forms\User;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\User;
use RuntimeException;
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
            $adminUser = Admin::user();
            if ($adminUser->cannot('user-delete')) {
                throw new RuntimeException('非法操作');
            }

            $id = (int)($this->payload['id'] ?? 0);
            $password = (string)($input['password'] ?? '');
            $google2faCode = (string)($input['google_2fa_code'] ?? '');

            if ($id <= 0) {
                throw new RuntimeException('金主参数错误');
            }
            if (!Hash::check($password, $adminUser->password)) {
                throw new RuntimeException('操作人登录密码错误');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);

            [$user, $deletedBankCount] = DB::transaction(function () use ($id, $adminUser) {
                $user = User::query()->select(['id', 'username', 'name', 'admin_user_id'])->whereKey($id)->lockForUpdate()->first();
                if (!$user) {
                    throw new RuntimeException('金主不存在');
                }

                // 记录删除操作人，并删除金主及收款卡。
                $user->admin_user_id = $adminUser->id;
                $user->save();
                $user->delete();
                $deletedBankCount = UserBank::query()->where('user_id', $user->id)->delete();

                return [$user, $deletedBankCount];
            });

            app(SystemLogService::class)->logAction(
                actionKey: 'user.delete',
                text: '删除 金主',
                subject: $user,
                properties: [
                    'user_id' => $user->id,
                    'username' => $user->username ?? null,
                    'name' => $user->name ?? null,
                    'deleted_user_banks' => $deletedBankCount,
                    'remark' => '删除金主及其收款卡',
                ],
                remark: sprintf('删除 金主（昵称:%s，账号:%s）', $user->name ?? '-', $user->username ?? '-'),
                logType: 'operation',
                actionMethod: 'DELETE',
                appType: 'admin',
                user: $adminUser
            );

            return $this->response()->success('删除成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-delete');
    }

    public function form()
    {
        $this->confirm('确认删除', '删除金主及其收款卡；历史流水和交易记录保留用于审计');
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
}

<?php

namespace App\Admin\Forms\UserBank;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\UserBank;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Cache\User\GetUserListService;
use App\Services\Cache\User\GetUserDetailService;
use App\Services\UserBank\UserBankActionLogService;
use App\Services\Cache\UserBank\GetUserBankDetailService;

class RecoveForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            if ($admin->cannot('user-bank-restore')) {
                throw new \Exception('无还原金主收款卡权限');
            }

            $id = $this->payload['id'] ?? 0;
            $userId = intval($input['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new \Exception('请选择金主');
            }

            // 还原收款卡并切换归属金主，日志保留还原前后的归属。
            [$model, $oldUserId, $remark] = DB::transaction(function () use ($id, $userId) {
                $model = UserBank::query()->withTrashed()->whereKey($id)->lockForUpdate()->first(['id', 'user_id', 'collection_status', 'deleted_at']);
                if (!$model) {
                    throw new \Exception('收款卡不存在');
                }

                $oldUserId = $model->user_id;
                $remark = $this->buildRecoverRemark($oldUserId, $userId);
                $model->user_id = $userId;
                $model->collection_status = 0;
                $model->restore();
                $model->save();

                return [$model, $oldUserId, $remark];
            });

            app(UserBankActionLogService::class)->excute(['type' => 2, 'type_id' => $admin->id, 'action' => 4, 'user_bank_id' => $model->id, 'remark' => $remark]);
            app(SystemLogService::class)->logAction(
                actionKey: 'userbank.recover',
                text: '还原 收款卡',
                subject: $model,
                properties: [
                    'user_bank_id' => $model->id,
                    'old_user_id' => $oldUserId,
                    'new_user_id' => $userId,
                    'remark' => $remark,
                ],
                remark: '还原 收款卡',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('还原成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-restore');
    }

    public function form()
    {
        $this->confirm('确认还原', '还原收款卡');
        $this->select('user_id', '选择金主')->options(collect(app(GetUserListService::class)->excute())->pluck('bname', 'id'))->required();
    }

    public function default()
    {
        $result = app(GetUserBankDetailService::class)->excute($this->payload['id'] ?? 0);

        return [
            'user_id' => $result['user_id'] ?? 0,
        ];
    }

    private function buildRecoverRemark(int $oldUserId, int $newUserId): string
    {
        if ($oldUserId === $newUserId) {
            return '';
        }

        $oldUserName = data_get(app(GetUserDetailService::class)->excute($oldUserId), 'bname', '');
        $newUserName = data_get(app(GetUserDetailService::class)->excute($newUserId), 'bname', '');

        return '源金主:' . $oldUserName . '，还原后金主:' . $newUserName;
    }
}

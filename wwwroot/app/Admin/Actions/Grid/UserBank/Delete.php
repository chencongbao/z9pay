<?php

namespace App\Admin\Actions\Grid\UserBank;

use Throwable;
use Dcat\Admin\Admin;
use App\Models\UserBank;
use Dcat\Admin\Grid\RowAction;
use Illuminate\Support\Facades\DB;
use App\Services\Common\SystemLogService;
use App\Services\Cache\UserBank\GetUserBankListService;
use App\Services\Cache\UserBank\GetUserBankDetailService;

class Delete extends RowAction
{
    protected $title = '<i class="feather icon-trash-2"></i> 删除收款卡';

    public function handle()
    {
        try {
            $admin = Admin::user();
            if ($admin->cannot('user-bank-delete')) {
                return $this->response()->error('无删除金主收款卡权限');
            }

            $id = $this->userBankId();
            $userBank = DB::transaction(function () use ($id) {
                $userBank = UserBank::query()->whereKey($id)->lockForUpdate()->first();
                if (!$userBank) {
                    throw new \Exception('收款卡不存在');
                }

                $userBank->delete();

                return $userBank;
            });

            app(GetUserBankDetailService::class)->excute($id, true);
            app(GetUserBankListService::class)->excute(true);

            app(SystemLogService::class)->logAction(
                actionKey: 'userbank.delete',
                text: '删除 收款卡',
                subject: $userBank,
                properties: [
                    'user_bank_id' => $userBank->id,
                    'user_id' => $userBank->user_id,
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

    public function confirm()
    {
        return ['确认删除', '删除后可在回收站还原'];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-delete');
    }

    private function userBankId(): int
    {
        $id = $this->getKey();
        if ((!is_int($id) && !is_string($id)) || !ctype_digit((string)$id) || (int)$id <= 0) {
            throw new \Exception('收款卡编号不合法');
        }

        return (int)$id;
    }
}

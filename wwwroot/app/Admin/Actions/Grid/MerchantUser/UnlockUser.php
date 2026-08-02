<?php

namespace App\Admin\Actions\Grid\MerchantUser;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\RowAction;
use Illuminate\Http\Request;
use App\Models\MerchantUser;
use App\Services\Common\SystemLogService;
use App\Services\Throttles\MerchantUserLoginThrottleService;

class UnlockUser extends RowAction
{
    protected $title = '<i class="feather icon-power"></i> 解锁登录';

    public function handle(Request $request)
    {
        $user = MerchantUser::query()
            ->with(['merchant_info' => function ($query) {
                $query->select(['merchant_user_id', 'name', 'coder']);
            }])
            ->find($this->getKey(), ['id', 'username']);
        if (!$user) {
            return $this->response()->error('非法操作');
        }

        app(MerchantUserLoginThrottleService::class)->unlockByUsername($user->username);

        $merchantInfo = $user->merchant_info;
        $nickname = (string)($merchantInfo->name ?? '');
        $coder = (string)($merchantInfo->coder ?? '');
        $remarkParts = array_filter([
            $nickname !== '' ? '昵称:' . $nickname : null,
            $coder !== '' ? '编码:' . $coder : null,
        ]);
        $remark = $remarkParts ? ('解锁 商户登录（' . implode('，', $remarkParts) . '）') : '解锁 商户登录';

        app(SystemLogService::class)->logAction(
            actionKey: 'merchant.user.unlock',
            text: '解锁 商户登录',
            subject: $user,
            properties: [
                'merchant_user_id' => $user->id,
                'username' => $user->username,
                'merchant_name' => $nickname,
                'coder' => $coder,
            ],
            remark: $remark,
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );

        return $this->response()->success('解锁成功');
    }

    public function confirm()
    {
        return ['确认操作?', '解锁商户登录'];
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-user-unlock-login');
    }
}

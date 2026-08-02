<?php

namespace App\Admin\Forms\MerchantUser;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\MerchantInfo;
use App\Models\MerchantUser;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\IpWhite\WhiteIpFormatService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Cache\Merchant\CacheMerchantWhiteIpByUsernameService;

class WhiteIpForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $adminUser = Admin::user();
            if ($adminUser->cannot('merchant-user-white-ip')) {
                throw new RuntimeException('非法操作');
            }

            $id = intval($this->payload['id'] ?? 0);
            $whiteIpFormatService = app(WhiteIpFormatService::class);
            $loginWhiteIp = $whiteIpFormatService->normalize($input['login_white_ip'] ?? '', '登录提现Ip白名单');
            $payWhiteIp = $whiteIpFormatService->normalize($input['pay_white_ip'] ?? '', '代付Ip白名单');
            $google2faCode = (string)($input['google_2fa_code'] ?? '');

            if ($id <= 0) {
                throw new RuntimeException('商户参数错误');
            }

            $user = MerchantUser::query()
                ->with(['merchant_info' => function ($query) {
                    $query->select(['merchant_user_id', 'name', 'coder', 'pay_white_ip']);
                }])
                ->whereKey($id)
                ->first(['id', 'username', 'login_white_ip']);
            if (!$user || !$user->merchant_info) {
                throw new RuntimeException('商户不存在');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);

            $merchantInfo = $user->merchant_info;
            $oldLoginWhiteIp = (string)($user->login_white_ip ?? '');
            $oldPayWhiteIp = (string)($merchantInfo->pay_white_ip ?? '');

            DB::transaction(function () use ($user, $merchantInfo, $loginWhiteIp, $payWhiteIp, $oldLoginWhiteIp, $oldPayWhiteIp, $adminUser) {
                $lockedUser = MerchantUser::query()->whereKey($user->id)->lockForUpdate()->first(['id']);
                $lockedMerchantInfo = MerchantInfo::query()->whereKey($merchantInfo->merchant_user_id)->lockForUpdate()->first(['merchant_user_id']);
                if (!$lockedUser || !$lockedMerchantInfo) {
                    throw new RuntimeException('商户不存在');
                }

                // 白名单已有专项审计日志；关闭模型通用日志，避免重复记录和半模型 old=null 的误导日志。
                $lockedUser->disableLogging()->forceFill(['login_white_ip' => $loginWhiteIp])->save();
                $lockedMerchantInfo->disableLogging()->forceFill(['pay_white_ip' => $payWhiteIp])->save();

                $nickname = (string)($merchantInfo->name ?? '');
                $coder = (string)($merchantInfo->coder ?? '');
                $remarkParts = array_filter([
                    $nickname !== '' ? '昵称:' . $nickname : null,
                    $coder !== '' ? '编码:' . $coder : null,
                ]);
                $remark = $remarkParts ? ('设置 商户白名单（' . implode('，', $remarkParts) . '）') : '设置 商户白名单';

                app(SystemLogService::class)->logAction(
                    actionKey: 'merchant.user.white_ip',
                    text: '设置 商户白名单',
                    subject: $merchantInfo,
                    properties: [
                        'merchant_user_id' => $user->id,
                        'merchant_name' => $nickname,
                        'coder' => $coder,
                        'old_login_white_ip' => $oldLoginWhiteIp,
                        'new_login_white_ip' => $loginWhiteIp,
                        'old_pay_white_ip' => $oldPayWhiteIp,
                        'new_pay_white_ip' => $payWhiteIp,
                    ],
                    remark: $remark,
                    logType: 'operation',
                    actionMethod: 'PUT',
                    appType: 'admin',
                    user: $adminUser
                );
            });

            $this->clearWhiteIpCache($user->id, (string)$user->username);

            return $this->response()->success('修改成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-user-white-ip');
    }

    public function form()
    {
        $this->display('name', '商户名称');
        $this->textarea('login_white_ip', '登录提现ip白名单')->placeholder('多个IP请用,隔开');
        $this->textarea('pay_white_ip', '代付ip白名单')->placeholder('多个IP请用,隔开');
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        $id = intval($this->payload['id'] ?? 0);
        if ($id <= 0) {
            return [
                'name' => '',
                'login_white_ip' => '',
                'pay_white_ip' => '',
            ];
        }

        $user = MerchantUser::query()
            ->with(['merchant_info' => function ($query) {
                $query->select(['merchant_user_id', 'name', 'pay_white_ip']);
            }])
            ->whereKey($id)
            ->first(['id', 'login_white_ip']);

        return [
            'name' => optional(optional($user)->merchant_info)->name,
            'login_white_ip' => optional($user)->login_white_ip,
            'pay_white_ip' => optional(optional($user)->merchant_info)->pay_white_ip,
        ];
    }

    private function clearWhiteIpCache(int $merchantUserId, string $username): void
    {
        try {
            app(CacheMerchantBaseInfoService::class)->excute($merchantUserId, true);
            if ($username !== '') {
                app(CacheMerchantWhiteIpByUsernameService::class)->forget($username);
            }
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning('merchant_white_ip_cache_clear_failed', [
                'error' => '修改商户白名单后清理缓存失败',
                'merchant_user_id' => $merchantUserId,
                'username' => $username,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Admin\Forms\MerchantUser;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\MerchantInfo;
use App\Models\MerchantUser;
use Dcat\Admin\Widgets\Form;
use App\Models\MerchantChannel;
use App\Models\MerchantPayment;
use Dcat\Admin\Traits\LazyWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Dcat\Admin\Contracts\LazyRenderable;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\Merchant\CacheApKeyService;
use App\Services\SystemNotice\SystemNoticeService;

class Delete extends Form implements LazyRenderable
{
    use LazyWidget;

    public function handle(array $input)
    {
        try {
            $adminUser = Admin::user();
            if ($adminUser->cannot('merchant-user-delete')) {
                throw new RuntimeException('非法操作');
            }

            $id = intval($this->payload['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('商户参数错误');
            }

            $merchant = MerchantInfo::query()->whereKey($id)->first(['merchant_user_id', 'appkey', 'coder', 'name']);
            if (!$merchant) {
                throw new RuntimeException('商户不存在');
            }

            $inputCoder = strtoupper((string)($input['coder'] ?? ''));
            $password = (string)($input['password'] ?? '');
            $google2faCode = (string)($input['google_2fa_code'] ?? '');
            if ($inputCoder !== strtoupper((string)$merchant->coder)) {
                throw new RuntimeException('商户代码不正确');
            }
            if (!Hash::check($password, $adminUser->password)) {
                throw new RuntimeException('操作人登录密码错误');
            }

            app(AdminGoogle2faService::class)->verify($google2faCode);

            $result = DB::transaction(function () use ($merchant, $adminUser) {
                $lockedMerchant = MerchantInfo::query()->whereKey($merchant->merchant_user_id)->lockForUpdate()->first(['merchant_user_id', 'appkey', 'coder', 'name']);
                if (!$lockedMerchant) {
                    throw new RuntimeException('商户不存在或已删除');
                }

                $paymentIds = MerchantPayment::query()->where('merchant_user_id', $lockedMerchant->merchant_user_id)->pluck('payment_id')->all();
                $channelPaymentIds = MerchantChannel::query()->where('merchant_user_id', $lockedMerchant->merchant_user_id)->pluck('payment_id')->all();
                $cachePaymentIds = array_values(array_unique(array_filter(array_map('intval', array_merge($paymentIds, $channelPaymentIds)))));

                // 删除商户信息时，同步删除主账号和所有子账号，避免商户端残留账号继续登录。
                $childUserIds = MerchantUser::query()->where('pid', $lockedMerchant->merchant_user_id)->lockForUpdate()->pluck('id')->map(fn ($id) => (int)$id)->all();
                $merchantUserIds = array_values(array_unique(array_merge([$lockedMerchant->merchant_user_id], $childUserIds)));

                $lockedMerchant->delete();
                $deletedMerchantUsers = MerchantUser::query()->whereIn('id', $merchantUserIds)->delete();
                $deletedPayments = MerchantPayment::query()->where('merchant_user_id', $lockedMerchant->merchant_user_id)->delete();
                $deletedChannels = MerchantChannel::query()->where('merchant_user_id', $lockedMerchant->merchant_user_id)->delete();

                $nickname = (string)($lockedMerchant->name ?? '');
                $merchantCoder = (string)($lockedMerchant->coder ?? '');
                $remarkParts = array_filter([
                    $nickname !== '' ? '昵称:' . $nickname : null,
                    $merchantCoder !== '' ? '编码:' . $merchantCoder : null,
                ]);
                $remark = $remarkParts ? ('删除 商户用户（' . implode('，', $remarkParts) . '）') : '删除 商户用户';

                app(SystemLogService::class)->logAction(
                    actionKey: 'merchant.user.delete',
                    text: '删除 商户用户',
                    subject: $lockedMerchant,
                    properties: [
                        'merchant_user_id' => $lockedMerchant->merchant_user_id,
                        'coder' => $merchantCoder,
                        'nickname' => $nickname,
                        'deleted_merchant_users' => $deletedMerchantUsers,
                        'deleted_child_user_ids' => $childUserIds,
                        'deleted_payments' => $deletedPayments,
                        'deleted_channels' => $deletedChannels,
                    ],
                    remark: $remark,
                    logType: 'operation',
                    actionMethod: 'DELETE',
                    appType: 'admin',
                    user: $adminUser
                );

                return [
                    'merchant_user_id' => $lockedMerchant->merchant_user_id,
                    'appkey' => (string)$lockedMerchant->appkey,
                    'payment_ids' => $cachePaymentIds,
                ];
            });

            $this->clearMerchantCache($result['merchant_user_id'], $result['appkey'], $result['payment_ids']);

            return $this->response()->success('删除成功')->refresh();
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-user-delete');
    }

    public function form()
    {
        $this->confirm('确认删除', '同时删除<商户账号><商户子账号><商户费率><商户渠道>相关数据');
        $this->text('coder', '被删除商户代码')->placeholder('请输入需要删除的商户代码')->required();
        $this->password('password', '操作人登录密码')->required()->help('用于确认本次敏感操作，不是被操作账号的密码');
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        return [
            'coder' => '',
            'password' => '',
            'google_2fa_code' => '',
        ];
    }

    private function clearMerchantCache(int $merchantUserId, string $appkey, array $paymentIds): void
    {
        try {
            app(CacheApKeyService::class)->removeCache($appkey);
            Cache::forget(CacheConstPrefixService::CACHE_MERCHANT_BASE_INFO . $merchantUserId);
            Cache::forget(CacheConstPrefixService::MERCHANT_PAYMENT_TRANSFER_RATE . $merchantUserId);
            foreach ($paymentIds as $paymentId) {
                Cache::forget(CacheConstPrefixService::MERCHANT_PAYMENT_DETAIL_LIST . $merchantUserId . '_' . $paymentId);
                Cache::forget(CacheConstPrefixService::MERCHANT_CHANNEL_DETAIL_LIST . $merchantUserId . '_' . $paymentId);
            }
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning('merchant_delete_cache_clear_failed', [
                'error' => '删除商户后清理商户缓存失败',
                'merchant_user_id' => $merchantUserId,
                'appkey' => $appkey,
                'payment_ids' => $paymentIds,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }
}

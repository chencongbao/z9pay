<?php

namespace App\Services\Google;

use Dcat\Admin\Admin;
use App\Models\AdminUser;
use App\Models\AgentUser;
use App\Models\MerchantUser;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Database\Eloquent\Model;
use App\Services\Common\SystemLogService;

class AdminResetGoogleService
{
    public function resetAdmin(AdminUser $user): void
    {
        $this->resetBind($user, true);
        $this->log(
            'admin.user.reset_google',
            '重置 管理员谷歌验证码',
            $user,
            ['admin_user_id' => $user->id, 'google_two_fa_bind' => 0],
            '重置 管理员谷歌验证码'
        );
    }

    public function resetMerchant(MerchantUser $user): void
    {
        $this->resetBind($user, true);

        $merchantInfo = $user->merchant_info;
        $nickname = (string)($merchantInfo->name ?? '');
        $coder = (string)($merchantInfo->coder ?? '');
        $remarkParts = array_filter([
            $nickname !== '' ? '昵称:' . $nickname : null,
            $coder !== '' ? '编码:' . $coder : null,
        ]);
        $remark = $remarkParts ? ('重置 商户谷歌验证（' . implode('，', $remarkParts) . '）') : '重置 商户谷歌验证';

        $this->log(
            'merchant.user.reset_google',
            '重置 商户谷歌验证',
            $user,
            ['merchant_user_id' => $user->id, 'merchant_name' => $nickname, 'coder' => $coder],
            $remark
        );
    }

    public function resetAgent(AgentUser $user): void
    {
        $this->resetBind($user, true);
        $this->log(
            'agent.user.reset_google',
            '重置 商户代理谷歌验证码',
            $user,
            ['agent_user_id' => $user->id, 'google_two_fa_bind' => 0],
            '重置 商户代理谷歌验证码'
        );
    }

    private function resetBind(Model $user, bool $refreshSecret): void
    {
        $data = ['google_two_fa_bind' => 0, 'session_id' => ''];
        if ($refreshSecret) {
            $data['google_two_fa_secret'] = (new Google2FA())->generateSecretKey(32);
        }

        $user->newQuery()->whereKey($user->getKey())->update($data);
        $user->forceFill($data);
    }

    private function log(string $actionKey, string $text, Model $subject, array $properties, string $remark): void
    {
        app(SystemLogService::class)->logAction(
            actionKey: $actionKey,
            text: $text,
            subject: $subject,
            properties: $properties,
            remark: $remark,
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );
    }
}

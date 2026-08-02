<?php

namespace App\MerchantAdmin\Form;

use App\Models\MerchantInfo;
use App\Models\MerchantUser;
use App\Services\Cache\Merchant\CacheApKeyService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Common\SystemLogService;
use App\Services\Google\AdminGoogle2faService;
use App\Services\IpWhite\CheckIpService;
use App\Services\Merchant\MerchantSecretAdminNoticeService;
use Dcat\Admin\Admin;
use Dcat\Admin\Contracts\LazyRenderable;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Request;

class ResetSecrectDetail extends Form implements LazyRenderable
{
    use LazyWidget;


    public function handle(array $input)
    {
        try {
            $password = $input['password'] ?? null;
            $google_2fa_code = $input['google_2fa_code'] ?? null;
            if(!Hash::check($password,Admin::user()->password))throw new \Exception($this->globalLabel("operator_login_password_error"));
            app(AdminGoogle2faService::class)->verify($google_2fa_code);
            $merchant_info = MerchantUser::select("login_white_ip")->find(bob_merchant_user_pid());
            if(!$merchant_info) throw new \Exception($this->handleField("illegal_operation"));
            if(empty($merchant_info->login_white_ip)){
                return throw new \Exception($this->handleLabel("set_login_ip"));
            }
            if(!empty($merchant_info->login_white_ip)){
                if(!App::make(CheckIpService::class)->excute(bob_format_muti_data_to_array($merchant_info->login_white_ip))){
                    return throw new \Exception($this->handleField("none_login_white_ip"));
                }
            }
            $merchantUserId = bob_merchant_user_pid();
            $merchant = MerchantInfo::where('merchant_user_id', $merchantUserId)->first(['appkey']);
            $oldAppkey = $merchant ? (string)$merchant->appkey : '';

            $appkey = bob_create_appkey();
            $appsecret = bob_create_app_secret();
            MerchantInfo::where('merchant_user_id', $merchantUserId)->update(['appkey' => $appkey, 'appsecret' => $appsecret]);
            app(MerchantSecretAdminNoticeService::class)->send('重置appkey', $merchantUserId, $appkey);

            if($oldAppkey !== ''){
                app(CacheApKeyService::class)->removeCache($oldAppkey);
            }
            App::make(CacheApKeyService::class)->excute($appkey,true);
            App::make(CacheMerchantBaseInfoService::class)->excute($merchantUserId,true);
            Request::session()->put('look_secret_detail',1);
            app(SystemLogService::class)->logAction(
                actionKey: 'merchant.secret.reset',
                text: '重置 API密钥信息',
                subject: Admin::user(),
                properties: ['merchant_user_id' => $merchantUserId],
                remark: '重置 API密钥信息',
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'merchant',
                user: Admin::user()
            );
            return $this->response()->success($this->handleLabel("reset_success"))->refresh();
        } catch (\Exception $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        if(Admin::user()->can("merchant_reset_secret")){
            return true;
        }
        return false;
    }

    public function form(){
        $this->display('username', __('admin.username'))->width(5,3);
        $this->password('password', $this->globalField('operator_login_password'))->same('password')->required()->width(5,3)->help($this->globalLabel('sensitive_operation_password_help'));
        app(AdminGoogle2faService::class)->appendField($this);
    }

    public function default()
    {
        return [
            'username' => Admin::user()->username,
            'password' => '',
            'google_2fa_code' => ''
        ];
    }

    private function handleLabel(string $key): string
    {
        $translationKey = "handle-form.labels.{$key}";

        return Lang::has($translationKey) ? __($translationKey) : admin_trans_label($key);
    }

    private function handleField(string $key): string
    {
        $translationKey = "handle-form.fields.{$key}";

        return Lang::has($translationKey) ? __($translationKey) : admin_trans_field($key);
    }

    private function globalField(string $key): string
    {
        $translationKey = "global.fields.{$key}";

        return Lang::has($translationKey) ? __($translationKey) : admin_trans_field($key);
    }

    private function globalLabel(string $key): string
    {
        $translationKey = "global.labels.{$key}";

        return Lang::has($translationKey) ? __($translationKey) : admin_trans_label($key);
    }
}

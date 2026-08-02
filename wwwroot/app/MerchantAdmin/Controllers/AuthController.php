<?php

namespace App\MerchantAdmin\Controllers;

use Throwable;
use Dcat\Admin\Admin;
use App\Rules\Captcha;
use Illuminate\Support\Arr;
use App\Models\MerchantInfo;
use App\Models\MerchantUser;
use Illuminate\Http\Request;
use Dcat\Admin\Layout\Content;
use App\Traits\ThrottlesLogins;
use Illuminate\Support\MessageBag;
use PragmaRX\Google2FA\Google2FA;
use Dcat\Admin\Http\Auth\Permission;
use App\Services\IpWhite\CheckIpService;
use App\Services\Common\SystemLogService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FAQRCode\QRCode\Bacon;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use App\Services\SystemNotice\SystemNoticeService;
use PragmaRX\Google2FAQRCode\Google2FA as QRGoogle2FA;
use Dcat\Admin\Http\Controllers\AuthController as BaseAuthController;

class AuthController extends BaseAuthController
{
    protected $view = 'merchant-admin.login';

    use ThrottlesLogins;

    public function username()
    {
        return 'username';
    }

    protected function throttlePrefix(): string
    {
        return 'merchant_admin';
    }

    public function getLogin(Content $content)
    {
        if ($this->guard()->check()) {
            return redirect($this->getRedirectPath());
        }
        if (payment_app_name() == 'lixiangpay') {
            return $content->full()->body(view('merchant-admin.lixiangpay-login'));
        }
        return $content->full()->body(view($this->view));
    }

    public function postLogin(Request $request)
    {
        $this->setLocaleFromCookie();

        if ($this->hasTooManyLoginAttempts($request)) {
            return $this->errorResponse($request, __('auth.merchant_login.too_many_attempts'));
        }

        $credentials = $request->only(['username', 'password', 'captcha', 'captchaType', 'coder']);
        $remember = (bool) $request->input('remember', false);
        $where = [
            'coder' => 'required',
            'username' => 'required',
            'password' => 'required',
            'captcha' => ['required', new Captcha($credentials)],
            'captchaType' => ['required', 'in:clickWord,blockPuzzle'],
        ];
        $validator = Validator::make($credentials, $where, [
            'coder.required' => __('auth.merchant_login.input_merchant_code'),
            'username.required' => __('auth.merchant_login.input_username'),
            'password.required' => __('auth.merchant_login.input_password'),
            'captcha.required' => __('auth.merchant_login.captcha_failed'),
            'captchaType.required' => __('auth.merchant_login.captcha_failed'),
            'captchaType.in' => __('auth.merchant_login.captcha_failed'),
        ]);
        if ($validator->fails()) {
            return $this->errorResponse($request, $validator->errors()->first());
        }

        $credentials['status'] = 1;
        if ($this->guard()->attempt(Arr::only($credentials, ['username', 'password', 'status']), $remember)) {
            $user = Admin::user();

            if ($message = $this->checkLoginWhiteIp($user)) {
                $this->guard()->logout();
                $this->writeLoginLog($user, 'fail', $message);
                return $this->errorResponse($request, $this->frontendLoginMessage($message));
            }

            if (!$this->merchantCoderExists((string) $credentials['coder'])) {
                $this->guard()->logout();
                $this->writeLoginLog($user, 'fail', '商户代码错误');
                return $this->errorResponse($request, __('auth.merchant_login.merchant_code_error'));
            }

            if ($this->hasLoginRisk($request) && empty(trim((string) $user->login_white_ip))) {
                $this->guard()->logout();
                $this->writeLoginLog($user, 'fail', '账号存在异常登录风险，未配置登录IP白名单');
                return $this->errorResponse($request, __('auth.merchant_login.login_risk_need_white_ip'));
            }

            if (config('default.disable_2fa')) {
                return $this->finishLogin($request, $user);
            }

            if ((int) $user->google_two_fa_bind === 0 && empty(trim((string) $user->login_white_ip))) {
                $this->guard()->logout();
                $this->writeLoginLog($user, 'fail', '未设置登录IP白名单，禁止进入谷歌绑定流程');
                return $this->errorResponse($request, __('auth.merchant_login.need_white_ip_before_google_bind'));
            }

            $payload = $this->twoFactorPayload($user);
            $this->guard()->logout();

            return $this->needTwoFactorResponse($request, $payload);
        }

        $this->recordLoginRisk($request);
        $this->incrementLoginAttempts($request);

        $this->writeLoginLog(null, 'fail', '账号或密码错误');
        bob_send_system_user_login_exception_notice((string) $request->input('username', ''), 'merchant');
        return $this->errorResponse($request, __('auth.merchant_login.auth_failed'));
    }

    public function postVerify(Request $request)
    {
        $this->setLocaleFromCookie();

        if ($this->hasTooManyLoginAttempts($request)) {
            return $this->errorResponse($request, __('auth.merchant_login.too_many_attempts'));
        }

        $credentials = $request->only(['coder', 'username', 'password', 'google_2fa_code']);
        $remember = (bool) $request->input('remember', false);
        $validatorCode = Validator::make($credentials, [
            'coder' => 'required',
            'username' => 'required',
            'password' => 'required',
            'google_2fa_code' => 'required|numeric|digits:6',
        ], [
            'coder.required' => __('auth.merchant_login.input_merchant_code'),
            'username.required' => __('auth.merchant_login.input_username'),
            'password.required' => __('auth.merchant_login.input_password'),
            'google_2fa_code.required' => __('auth.merchant_login.input_google_code'),
            'google_2fa_code.numeric' => __('auth.merchant_login.input_google_code'),
            'google_2fa_code.digits' => __('auth.merchant_login.input_google_code'),
        ]);
        if ($validatorCode->fails()) {
            $this->writeLoginLog(null, 'fail', '谷歌验证码格式错误');
            return $this->validationErrorResponse($request, $validatorCode->errors()->first());
        }

        $request->merge([$this->username() => (string) $request->input('username')]);
        $attemptCredentials = [
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'status' => 1,
        ];

        if (!$this->guard()->attempt($attemptCredentials, $remember)) {
            $this->recordLoginRisk($request);
            $this->incrementLoginAttempts($request);
            $this->writeLoginLog(null, 'fail', '账号或密码错误');
            bob_send_system_user_login_exception_notice((string) $request->input('username', ''), 'merchant');
            return $this->errorResponse($request, __('auth.merchant_login.auth_failed'));
        }

        $user = Admin::user();
        if (!$user) {
            return $this->successRedirectResponse($request, '/' . ltrim(config('merchant-admin.route.prefix') . '/auth/login', '/'));
        }

        if ($message = $this->checkLoginWhiteIp($user)) {
            $this->guard()->logout();
            $this->writeLoginLog($user, 'fail', $message);
            return $this->errorResponse($request, $this->frontendLoginMessage($message));
        }

        if (!$this->merchantCoderExists((string) $credentials['coder'])) {
            $this->guard()->logout();
            $this->writeLoginLog($user, 'fail', '商户代码错误');
            return $this->errorResponse($request, __('auth.merchant_login.merchant_code_error'));
        }

        if ($this->hasLoginRisk($request) && empty(trim((string) $user->login_white_ip))) {
            $this->guard()->logout();
            $this->writeLoginLog($user, 'fail', '账号存在异常登录风险，未配置登录IP白名单');
            return $this->errorResponse($request, __('auth.merchant_login.login_risk_need_white_ip'));
        }

        if ((int) $user->google_two_fa_bind === 0 && empty(trim((string) $user->login_white_ip))) {
            $this->guard()->logout();
            $this->writeLoginLog($user, 'fail', '未设置登录IP白名单，禁止进入谷歌绑定流程');
            return $this->errorResponse($request, __('auth.merchant_login.need_white_ip_before_google_bind'));
        }

        $google2fa = new Google2FA();
        if (!$google2fa->verifyKey($user->google_two_fa_secret, (string) $request->input('google_2fa_code'))) {
            $this->guard()->logout();
            $this->incrementLoginAttempts($request);
            $this->writeLoginLog($user, 'fail', '谷歌验证码不正确');
            return $this->validationErrorResponse($request, __('auth.merchant_login.google_code_error'));
        }
        $request->session()->migrate(true);

        return $this->finishLogin($request, $user, (int) $user->google_two_fa_bind === 0);
    }

    private function finishLogin(Request $request, MerchantUser $user, bool $bindGoogle = false)
    {
        $update = [
            'last_login_ip' => bob_ip(),
            'last_login_time' => date('Y-m-d H:i:s'),
            'session_id' => session()->getId(),
        ];
        if ($bindGoogle) {
            $update['google_two_fa_bind'] = 1;
        }

        MerchantUser::where('id', $user->id)->update($update);

        $this->clearLoginAttempts($request);
        $this->clearLoginRisk($request);
        $this->writeLoginLog($user, 'success', '登录成功');

        return $this->successRedirectResponse($request, $this->getRedirectPath());
    }

    private function twoFactorPayload(MerchantUser $user): array
    {
        $payload = [
            'need_2fa' => 1,
            'bind' => (int) $user->google_two_fa_bind,
            'qr' => '',
        ];

        if ((int) $user->google_two_fa_bind !== 0) {
            return $payload;
        }

        $pid = $user->pid > 0 ? $user->pid : $user->id;
        $merchantInfo = MerchantInfo::where('merchant_user_id', $pid)->first(['merchant_user_id', 'coder']);
        $google2fa = new QRGoogle2FA(new Bacon(new SvgImageBackEnd()));
        $payload['qr'] = $google2fa->getQRCodeInline(config('default.name'), strtoupper($merchantInfo->coder) . '@' . $user->username, $this->googleSecret($user));

        return $payload;
    }

    private function googleSecret(MerchantUser $user): string
    {
        if (!empty($user->google_two_fa_secret)) {
            return $user->google_two_fa_secret;
        }

        $secret = (new Google2FA())->generateSecretKey(32);
        MerchantUser::where('id', $user->id)->update(['google_two_fa_secret' => $secret]);

        return $secret;
    }

    private function checkLoginWhiteIp(MerchantUser $user): ?string
    {
        if (empty($user->login_white_ip)) {
            return null;
        }

        try {
            $allowIps = bob_format_muti_data_to_array($user->login_white_ip);
            if (app(CheckIpService::class)->excute($allowIps)) {
                return null;
            }

            app(SystemNoticeService::class)->warning('system_manual_notice', ['message' => '登陆IP地址不在白名单', '后台IP' => $allowIps, 'ip' => bob_ip(), 'user_id' => $user->id]);
            return '登陆IP地址不在白名单';
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning('system_manual_notice', ['message' => 'IP 白名单检测异常：' . $e->getMessage(), 'ip' => bob_ip(), 'user_id' => $user->id]);
            return 'IP白名单检测异常';
        }
    }

    private function setLocaleFromCookie(): void
    {
        if (Cookie::has('locale')) {
            App::setLocale((string) Cookie::get('locale'));
        }
    }

    private function frontendLoginMessage(string $message): string
    {
        return match ($message) {
            'IP白名单检测异常' => __('auth.merchant_login.system_error_retry'),
            '登陆IP地址不在白名单' => __('auth.merchant_login.ip_not_in_white_list'),
            default => $message,
        };
    }

    private function merchantCoderExists(string $coder): bool
    {
        return MerchantInfo::where('merchant_user_id', bob_merchant_user_pid())
            ->where('coder', $coder)
            ->whereHas('merchant_user', function ($query) {
                $query->where('status', 1);
            })
            ->exists();
    }

    private function writeLoginLog(?MerchantUser $user, string $result, string $message): void
    {
        $actionKey = $result === 'success' ? 'merchant.login.success' : 'merchant.login.fail';
        $text = $result === 'success' ? '登录成功' : '登录失败';
        $req = request();
        $detailMessage = $message;
        if ($result !== 'success') {
            $username = (string) $req->input('username', '');
            $coder = (string) $req->input('coder', '');
            if ($username !== '' || $coder !== '') {
                $detailMessage .= ' | 商户编码:' . ($coder !== '' ? $coder : '-')
                    . ' 账号:' . ($username !== '' ? $username : '-')
                    . ' 密码:[FILTERED]';
            }
        }
        $requestInput = $this->filteredLoginRequestInput($req);
        app(SystemLogService::class)->manual(
            'merchant',
            $actionKey,
            $text,
            $user,
            $user,
            [
                'result' => $result,
                'message' => $message,
            ],
            $detailMessage,
            $requestInput,
            bob_ip(),
            $req->method(),
            '/' . ltrim($req->path(), '/'),
            $req->userAgent(),
            'login'
        );
    }

    private function filteredLoginRequestInput(Request $request): array
    {
        $input = $request->all();
        foreach (['password', 'password_confirmation', 'google_2fa_code', 'captcha', '_token'] as $key) {
            if (array_key_exists($key, $input)) {
                $input[$key] = '[FILTERED]';
            }
        }

        return $input;
    }

    protected function settingForm()
    {
        Permission::error();
    }

    protected function errorResponse(Request $request, string $message)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'status' => false,
                'message' => $message,
            ]);
        }

        return $this->response()->error($message)->send();
    }

    protected function successRedirectResponse(Request $request, string $redirect)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'status' => true,
                'redirect' => $redirect,
            ]);
        }

        return redirect($redirect);
    }

    protected function needTwoFactorResponse(Request $request, array $data)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'status' => true,
                'data' => $data,
            ]);
        }

        return $this->response()->redirect('auth/verify');
    }

    protected function validationErrorResponse(Request $request, string $message)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'status' => false,
                'message' => $message,
            ], 422);
        }

        return $this->response()->withValidation(new MessageBag(['google_2fa_code' => [$message]]))->send();
    }
}

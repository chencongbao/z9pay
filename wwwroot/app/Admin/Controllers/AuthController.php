<?php

namespace App\Admin\Controllers;

use Throwable;
use Dcat\Admin\Admin;
use App\Rules\Captcha;
use App\Models\AdminUser;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Dcat\Admin\Layout\Content;
use App\Traits\ThrottlesLogins;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\App;
use Dcat\Admin\Models\Administrator;
use App\Services\IpWhite\CheckIpService;
use App\Services\Common\SystemLogService;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FAQRCode\QRCode\Bacon;
use App\Jobs\WarmAdminHomeDashboardCacheJob;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use Dcat\Admin\Http\Controllers\AuthController as BaseAuthController;
use App\Services\SystemNotice\SystemNoticeService;

class AuthController extends BaseAuthController
{
    use ThrottlesLogins;

    protected $view = 'admin.login';

    public function username()
    {
        return 'username';
    }

    protected function throttlePrefix(): string
    {
        return 'admin';
    }


    public function getLogin(Content $content)
    {
        if ($this->guard()->check()) {
            return redirect($this->getRedirectPath());
        }

        return $content->full()->body(view($this->view));
    }

    public function postLogin(Request $request)
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            return $this->errorResponse($request, '登录失败次数过多，请稍后再试');
        }

        $credentials = $request->only(['username', 'password', 'captcha', 'captchaType']);
        $remember = (bool) $request->input('remember', false);
        $where = [
            'username' => 'required',
            'password' => 'required',
            'captcha' => ['required', new Captcha($credentials)],
            'captchaType' => ['required', 'in:clickWord,blockPuzzle'],
        ];
        $validator = Validator::make($credentials, $where, ['captcha.required' => '滑动验证失败，请重新验证', 'captchaType.required' => '滑动验证失败，请重新验证']);
        if ($validator->fails()) {
            return $this->errorResponse($request, $validator->errors()->first());
        }

        $credentials['status'] = 1;
        if ($this->guard()->attempt(Arr::only($credentials, ['username', 'password', 'status']), $remember)) {
            $user = Admin::user();

            $whiteIpResponse = $this->checkLoginWhiteIp($request, $user);
            if ($whiteIpResponse) {
                return $whiteIpResponse;
            }

            if ($this->hasLoginRisk($request) && empty(trim((string) $user->login_white_ip))) {
                $this->guard()->logout();
                $this->writeLoginLog($user, 'fail', '账号存在异常登录风险，未配置登录IP白名单');
                return $this->errorResponse($request, '当前账号存在异常登录风险，请使用白名单IP登录或联系管理员配置登录白名单');
            }

            if (config('default.disable_2fa')) {
                $this->handleLoginSuccess($request, $user);
                return $this->successRedirectResponse($request, $this->getRedirectPath());
            }

            if ((int) $user->google_two_fa_bind === 0 && empty(trim((string) $user->login_white_ip))) {
                $this->guard()->logout();
                $this->writeLoginLog($user, 'fail', '未设置登录IP白名单，禁止进入谷歌绑定流程');
                return $this->errorResponse($request, '请先联系超级管理员设置登录IP白名单后，再进行谷歌绑定');
            }

            $payload = [
                'need_2fa' => 1,
                'bind' => (int) $user->google_two_fa_bind,
                'qr' => '',
            ];

            if ((int) $user->google_two_fa_bind === 0) {
                $baconService = new Bacon(new SvgImageBackEnd());
                $google2fa = new \PragmaRX\Google2FAQRCode\Google2FA($baconService);
                $google_two_fa_secret = !empty($user->google_two_fa_secret)
                    ? $user->google_two_fa_secret
                    : (new Google2FA())->generateSecretKey(32);
                if (empty($user->google_two_fa_secret)) {
                    AdminUser::whereKey($user->id)->update(['google_two_fa_secret' => $google_two_fa_secret]);
                }
                $payload['qr'] = $google2fa->getQRCodeInline(config('admin.name'), $user->username, $google_two_fa_secret);
            }

            $this->guard()->logout();

            return $this->needTwoFactorResponse($request, $payload);
        }

        $this->recordLoginRisk($request);
        $this->incrementLoginAttempts($request);
        $this->writeLoginLog(null, 'fail', '账号或密码错误');
        bob_send_system_user_login_exception_notice((string) $request->input('username', ''), 'system');

        return $this->errorResponse($request, '账号或密码错误');
    }

    public function postVerify(Request $request)
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            return $this->errorResponse($request, '登录失败次数过多，请稍后再试');
        }

        $credentials = $request->only(['username', 'password', 'google_2fa_code']);
        $remember = (bool) $request->input('remember', false);
        $where = [
            'username' => 'required',
            'password' => 'required',
            'google_2fa_code' => 'required|numeric|digits:6',
        ];
        $validator = Validator::make($credentials, $where, [
            'username.required' => '请输入账号',
            'password.required' => '请输入密码',
            'google_2fa_code.required' => '请输入谷歌验证码',
            'google_2fa_code.numeric' => '请输入谷歌验证码',
            'google_2fa_code.digits' => '请输入谷歌验证码',
        ]);
        if ($validator->fails()) {
            $this->writeLoginLog(null, 'fail', '登录验证错误');
            return $this->validationErrorResponse($request, $validator->errors()->first());
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
            bob_send_system_user_login_exception_notice((string) $request->input('username', ''), 'system');
            return $this->errorResponse($request, '账号或密码错误');
        }

        $user = Admin::user();
        if (!$user) {
            return $this->successRedirectResponse($request, admin_url('auth/login'));
        }

        $whiteIpResponse = $this->checkLoginWhiteIp($request, $user);
        if ($whiteIpResponse) {
            return $whiteIpResponse;
        }

        if ($this->hasLoginRisk($request) && empty(trim((string) $user->login_white_ip))) {
            $this->guard()->logout();
            $this->writeLoginLog($user, 'fail', '账号存在异常登录风险，未配置登录IP白名单');
            return $this->errorResponse($request, '当前账号存在异常登录风险，请使用白名单IP登录或联系管理员配置登录白名单');
        }

        if ((int) $user->google_two_fa_bind === 0 && empty(trim((string) $user->login_white_ip))) {
            $this->guard()->logout();
            $this->writeLoginLog($user, 'fail', '未设置登录IP白名单，禁止进入谷歌绑定流程');
            return $this->errorResponse($request, '请先联系超级管理员设置登录IP白名单后，再进行谷歌绑定');
        }

        $google_2fa_code = (string) $request->input('google_2fa_code');
        $google2fa = new Google2FA();
        if (!$google2fa->verifyKey($user->google_two_fa_secret, $google_2fa_code)) {
            $this->guard()->logout();
            $this->incrementLoginAttempts($request);
            $this->writeLoginLog($user, 'fail', '验证码不正确');
            return $this->validationErrorResponse($request, '您输入的验证码不正确');
        }

        $request->session()->migrate(true);

        $this->handleLoginSuccess($request, $user, true);

        return $this->successRedirectResponse($request, $this->getRedirectPath());
    }

    private function checkLoginWhiteIp(Request $request, Administrator $user)
    {
        if (!$this->shouldCheckLoginWhiteIp() || empty($user->login_white_ip)) {
            return null;
        }

        try {
            $allowIps = bob_format_muti_data_to_array($user->login_white_ip);
            if (App::make(CheckIpService::class)->excute($allowIps)) {
                return null;
            }

            app(SystemNoticeService::class)->warning('system_manual_notice', ['message' => '登陆IP地址不在白名单', '后台IP' => $allowIps, 'ip' => bob_ip(), 'user_id' => $user->id]);
            $this->guard()->logout();
            $this->writeLoginLog($user, 'fail', '登陆IP地址不在白名单');
            return $this->errorResponse($request, '登陆IP地址不在白名单');
        } catch (Throwable $e) {
            app(SystemNoticeService::class)->warning('system_manual_notice', ['message' => 'IP 白名单检测异常：' . $e->getMessage(), 'ip' => bob_ip(), 'user_id' => $user->id]);
            $this->guard()->logout();
            $this->writeLoginLog($user, 'fail', 'IP白名单检测异常');
            return $this->errorResponse($request, '系统异常，请稍后重试');
        }
    }

    private function handleLoginSuccess(Request $request, Administrator $user, bool $bindGoogleTwoFa = false): void
    {
        $update = [
            'last_login_ip' => bob_ip(),
            'last_login_time' => date('Y-m-d H:i:s'),
            'session_id' => session()->getId(),
        ];
        if ($bindGoogleTwoFa && (int) $user->google_two_fa_bind === 0) {
            $update['google_two_fa_bind'] = 1;
        }
        AdminUser::whereKey($user->id)->update($update);

        $this->clearLoginAttempts($request);
        $this->clearLoginRisk($request);
        $this->writeLoginLog($user, 'success', '登录成功');
        WarmAdminHomeDashboardCacheJob::dispatch((int) $user->id)->afterResponse();
    }

    private function writeLoginLog(?Administrator $user, string $result, string $message): void
    {
        $actionKey = $result === 'success' ? 'admin.login.success' : 'admin.login.fail';
        $text = $result === 'success' ? '登录成功' : '登录失败';
        $req = request();
        $detailMessage = $message;
        if ($result !== 'success') {
            $username = (string) $req->input('username', '');
            if ($username !== '') {
                $detailMessage .= ' | 账号:' . ($username !== '' ? $username : '-')
                    . ' 密码:[FILTERED]';
            }
        }
        $requestInput = $req->except(['password']);
        if (array_key_exists('password', $req->all())) {
            $requestInput['password'] = '[FILTERED]';
        }
        app(SystemLogService::class)->manual(
            'admin',
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

    private function shouldCheckLoginWhiteIp(): bool
    {
        return !App::environment(['local']) && !config('app.debug');
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

<?php

namespace App\AgentAdmin\Controllers;

use Throwable;
use Dcat\Admin\Form;
use Dcat\Admin\Admin;
use App\Rules\Captcha;
use App\Models\AgentUser;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Dcat\Admin\Layout\Content;
use App\Traits\ThrottlesLogins;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use PragmaRX\Google2FAQRCode\Google2FA as QRGoogle2FA;
use App\Services\IpWhite\CheckIpService;
use App\Services\Common\SystemLogService;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FAQRCode\QRCode\Bacon;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use Dcat\Admin\Http\Controllers\AuthController as BaseAuthController;
use App\Services\SystemNotice\SystemNoticeService;

class AuthController extends BaseAuthController
{
    use ThrottlesLogins;

    protected const PENDING_TWO_FACTOR_SESSION_KEY = 'agent_admin.pending_two_factor';
    protected const PENDING_TWO_FACTOR_TTL_MINUTES = 5;

    protected $view = 'agent-admin.login';

    public function username()
    {
        return 'username';
    }

    protected function throttlePrefix(): string
    {
        return 'agent_admin';
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
        $this->setLocaleFromCookie();

        if ($this->hasTooManyLoginAttempts($request)) {
            return $this->errorResponse($request, __('auth.agent_login.too_many_attempts'));
        }

        $credentials = $request->only(['username', 'password', 'captcha', 'captchaType']);
        $remember = (bool) $request->input('remember', false);
        $validator = Validator::make($credentials, [
            'username' => 'required',
            'password' => 'required',
            'captcha' => ['required', new Captcha($credentials)],
            'captchaType' => ['required', 'in:clickWord,blockPuzzle'],
        ], [
            'username.required' => __('auth.agent_login.input_username'),
            'password.required' => __('auth.agent_login.input_password'),
            'captcha.required' => __('auth.agent_login.captcha_failed'),
            'captchaType.required' => __('auth.agent_login.captcha_failed'),
            'captchaType.in' => __('auth.agent_login.captcha_failed'),
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

            if ($this->hasLoginRisk($request) && empty(trim((string) $user->login_white_ip))) {
                $this->guard()->logout();
                $this->writeLoginLog($user, 'fail', '账号存在异常登录风险，未配置登录IP白名单');
                return $this->errorResponse($request, __('auth.agent_login.login_risk_need_white_ip'));
            }

            if (config('default.disable_2fa')) {
                return $this->finishLogin($request, $user);
            }

            if ((int) $user->google_two_fa_bind === 0 && empty(trim((string) $user->login_white_ip))) {
                $this->guard()->logout();
                $this->writeLoginLog($user, 'fail', '未设置登录IP白名单，禁止进入谷歌绑定流程');
                return $this->errorResponse($request, __('auth.agent_login.need_white_ip_before_google_bind'));
            }

            $payload = $this->twoFactorPayload($user);
            $this->storePendingTwoFactorChallenge($request, (string) $user->username);
            $this->guard()->logout();

            return $this->needTwoFactorResponse($request, $payload);
        }

        $this->recordLoginRisk($request);
        $this->incrementLoginAttempts($request);
        $this->writeLoginLog(null, 'fail', '账号或密码错误');
        bob_send_system_user_login_exception_notice((string) $request->input('username', ''), 'agent');

        return $this->errorResponse($request, __('auth.agent_login.auth_failed'));
    }

    public function postVerify(Request $request)
    {
        $this->setLocaleFromCookie();

        if (!$this->hasValidPendingTwoFactorChallenge($request, (string) $request->input('username', ''))) {
            return $this->validationErrorResponse($request, __('auth.agent_login.verify_session_expired'));
        }

        if ($this->hasTooManyLoginAttempts($request)) {
            return $this->errorResponse($request, __('auth.agent_login.too_many_attempts'));
        }

        $credentials = $request->only(['username', 'password', 'google_2fa_code']);
        $remember = (bool) $request->input('remember', false);
        $validatorCode = Validator::make($credentials, [
            'username' => 'required',
            'password' => 'required',
            'google_2fa_code' => 'required|numeric|digits:6',
        ], [
            'username.required' => __('auth.agent_login.input_username'),
            'password.required' => __('auth.agent_login.input_password'),
            'google_2fa_code.required' => __('auth.agent_login.input_google_code'),
            'google_2fa_code.numeric' => __('auth.agent_login.input_google_code'),
            'google_2fa_code.digits' => __('auth.agent_login.input_google_code'),
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
            bob_send_system_user_login_exception_notice((string) $request->input('username', ''), 'agent');
            return $this->errorResponse($request, __('auth.agent_login.auth_failed'));
        }

        $user = Admin::user();
        if (!$user) {
            $this->clearPendingTwoFactorChallenge($request);
            return $this->successRedirectResponse($request, '/' . ltrim(config('agent-admin.route.prefix') . '/auth/login', '/'));
        }

        if ($message = $this->checkLoginWhiteIp($user)) {
            $this->guard()->logout();
            $this->writeLoginLog($user, 'fail', $message);
            return $this->errorResponse($request, $this->frontendLoginMessage($message));
        }

        if ($this->hasLoginRisk($request) && empty(trim((string) $user->login_white_ip))) {
            $this->guard()->logout();
            $this->writeLoginLog($user, 'fail', '账号存在异常登录风险，未配置登录IP白名单');
            return $this->errorResponse($request, __('auth.agent_login.login_risk_need_white_ip'));
        }

        if ((int) $user->google_two_fa_bind === 0 && empty(trim((string) $user->login_white_ip))) {
            $this->guard()->logout();
            $this->writeLoginLog($user, 'fail', '未设置登录IP白名单，禁止进入谷歌绑定流程');
            return $this->errorResponse($request, __('auth.agent_login.need_white_ip_before_google_bind'));
        }

        if (!(new Google2FA())->verifyKey($user->google_two_fa_secret, (string) $request->input('google_2fa_code'))) {
            $this->guard()->logout();
            $this->incrementLoginAttempts($request);
            $this->writeLoginLog($user, 'fail', '谷歌验证码不正确');
            return $this->validationErrorResponse($request, __('auth.agent_login.google_code_error'));
        }

        $request->session()->migrate(true);

        return $this->finishLogin($request, $user, (int) $user->google_two_fa_bind === 0);
    }

    private function finishLogin(Request $request, AgentUser $user, bool $bindGoogle = false)
    {
        $update = [
            'last_login_ip' => bob_ip(),
            'last_login_time' => date('Y-m-d H:i:s'),
            'session_id' => session()->getId(),
        ];
        if ($bindGoogle) {
            $update['google_two_fa_bind'] = 1;
        }

        AgentUser::whereKey($user->id)->update($update);

        $this->clearLoginAttempts($request);
        $this->clearLoginRisk($request);
        $this->writeLoginLog($user, 'success', '登录成功');
        $this->clearPendingTwoFactorChallenge($request);

        return $this->successRedirectResponse($request, $this->getRedirectPath());
    }

    protected function storePendingTwoFactorChallenge(Request $request, string $username): void
    {
        $request->session()->put(self::PENDING_TWO_FACTOR_SESSION_KEY, [
            'username' => $this->normalizeUser($username),
            'expires_at' => now()->addMinutes(self::PENDING_TWO_FACTOR_TTL_MINUTES)->timestamp,
        ]);
    }

    protected function hasValidPendingTwoFactorChallenge(Request $request, string $username): bool
    {
        $challenge = $request->session()->get(self::PENDING_TWO_FACTOR_SESSION_KEY);
        $valid = is_array($challenge)
            && hash_equals((string) ($challenge['username'] ?? ''), $this->normalizeUser($username))
            && (int) ($challenge['expires_at'] ?? 0) > now()->timestamp;

        if (!$valid) {
            $this->clearPendingTwoFactorChallenge($request);
        }

        return $valid;
    }

    protected function clearPendingTwoFactorChallenge(Request $request): void
    {
        $request->session()->forget(self::PENDING_TWO_FACTOR_SESSION_KEY);
    }

    private function twoFactorPayload(AgentUser $user): array
    {
        $payload = [
            'need_2fa' => 1,
            'bind' => (int) $user->google_two_fa_bind,
            'qr' => '',
        ];

        if ((int) $user->google_two_fa_bind !== 0) {
            return $payload;
        }

        $google2fa = new QRGoogle2FA(new Bacon(new SvgImageBackEnd()));
        $payload['qr'] = $google2fa->getQRCodeInline(config('default.name'), $user->username, $this->googleSecret($user));

        return $payload;
    }

    private function googleSecret(AgentUser $user): string
    {
        if (!empty($user->google_two_fa_secret)) {
            return $user->google_two_fa_secret;
        }

        $secret = (new Google2FA())->generateSecretKey(32);
        AgentUser::whereKey($user->id)->update(['google_two_fa_secret' => $secret]);

        return $secret;
    }

    private function checkLoginWhiteIp(AgentUser $user): ?string
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
            'IP白名单检测异常' => __('auth.agent_login.system_error_retry'),
            '登陆IP地址不在白名单' => __('auth.agent_login.ip_not_in_white_list'),
            default => $message,
        };
    }

    private function writeLoginLog(?AgentUser $user, string $result, string $message): void
    {
        $actionKey = $result === 'success' ? 'agent.login.success' : 'agent.login.fail';
        $text = $result === 'success' ? '登录成功' : '登录失败';
        $req = request();
        $detailMessage = $message;
        if ($result !== 'success') {
            $username = (string) $req->input('username', '');
            if ($username !== '') {
                $detailMessage .= ' | 账号:' . $username . ' 密码:[FILTERED]';
            }
        }
        $requestInput = $req->except(['password']);
        if (array_key_exists('password', $req->all())) {
            $requestInput['password'] = '[FILTERED]';
        }
        app(SystemLogService::class)->manual(
            'agent',
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

    protected function settingForm()
    {
        return new Form(new AgentUser(), function (Form $form) {
            $prefix = trim((string) config('agent-admin.route.prefix'), '/');
            $form->action(url('/' . ($prefix ? $prefix . '/' : '') . 'auth/setting'));

            $form->disableCreatingCheck();
            $form->disableEditingCheck();
            $form->disableViewCheck();

            $form->tools(function (Form\Tools $tools) {
                $tools->disableView();
                $tools->disableDelete();
            });

            $form->display('username', trans('admin.username'));
            $form->text('name', trans('admin.name'))->required();
            $form->image('avatar', trans('admin.avatar'))->autoUpload();
            $form->password('old_password', trans('admin.old_password'));
            $form->password('password', trans('admin.password'))
                ->minLength(5)
                ->maxLength(20)
                ->customFormat(function ($value) {
                    if ($value == $this->password) {
                        return;
                    }

                    return $value;
                });
            $form->password('password_confirmation', trans('admin.password_confirmation'))->same('password');
            $form->ignore(['password_confirmation', 'old_password']);

            $form->saving(function (Form $form) {
                if ($form->password && $form->model()->password != $form->password) {
                    $form->password = bcrypt($form->password);
                }

                if (!$form->password) {
                    $form->deleteInput('password');
                }
            });

            $form->saved(function (Form $form) {
                return $form
                    ->response()
                    ->success(trans('admin.update_succeeded'))
                    ->redirect('auth/setting');
            });
        });
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

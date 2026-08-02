<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V2\HomeCheckGoogleVcodeRequest;
use App\Http\Requests\Api\V2\HomeCheckLoginRequest;
use App\Models\User;
use App\Services\Common\SystemLogService;
use App\Traits\ThrottlesLogins;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Mews\Captcha\Captcha;
use PragmaRX\Google2FA\Google2FA;
use PragmaRX\Google2FAQRCode\QRCode\Bacon;

class HomeController extends ApiController
{

    use ThrottlesLogins;

    private const FILTERED_VALUE = '[FILTERED]';

    private const LOGIN_STAGE_TTL_SECONDS = 300;

    public function username()
    {
        return 'username';
    }

    protected function throttlePrefix(): string
    {
        return 'user_api_v2';
    }

    protected function logAuth(Request $request, string $message, array $extra = [], ?User $subject = null): void
    {
        $username = (string) $request->input($this->username(), '');
        $key = $username !== '' ? $this->lockKey($request) : null;

        $base = [
            'endpoint'     => $request->path(),
            'ip'           => $request->ip(),
            'username'     => $username ?: null,
            'throttle_key' => $key,
        ];

        if ($key) {
            $base['attempts'] = $this->limiter()->attempts($key);
            $base['available_in'] = $this->limiter()->availableIn($key);
        }

        $context = array_merge($base, $extra);
        $eventType = (string)($context['type'] ?? 'event');
        // 成功类登录不记录
        if (in_array($eventType, ['login_ok', 'password_ok', 'need_bind_2fa'], true)) {
            return;
        }

        $actionKey = match ($eventType) {
            'too_many_attempts' => 'api.user.login.too_many_attempts',
            'user_not_found' => 'api.user.login.user_not_found',
            'disabled' => 'api.user.login.disabled',
            'password_wrong' => 'api.user.login.password_wrong',
            '2fa_wrong' => 'api.user.login.2fa_wrong',
            '2fa_wrong_default' => 'api.user.login.2fa_wrong_default',
            'login_stage_missing' => 'api.user.login.stage_missing',
            'need_bind_2fa' => 'api.user.login.need_bind_2fa',
            'password_ok' => 'api.user.login.password_ok',
            'login_ok' => 'api.user.login.success',
            default => 'api.user.login.event',
        };

        $desc = $message
            . ' | 用户名:' . ($username !== '' ? $username : '-')
            . ' | 密码:' . ($request->filled('password') ? self::FILTERED_VALUE : '-');

        app(SystemLogService::class)->manual(
            appType: 'user',
            actionKey: $actionKey,
            text: $desc,
            subject: $subject,
            user: $subject,
            properties: $context,
            remark: $desc,
            requestInput: $this->filteredAuthInput($request),
            ip: bob_ip(),
            method: $request->method(),
            path: '/' . ltrim($request->path(), '/'),
            userAgent: $request->userAgent(),
            logType: 'login'
        );
    }

    private function filteredAuthInput(Request $request): array
    {
        $input = $request->all();
        foreach (['password', 'password_confirmation', 'google_2fa_code', 'captcha', '_token'] as $key) {
            if (array_key_exists($key, $input)) {
                $input[$key] = self::FILTERED_VALUE;
            }
        }

        return $input;
    }


    public function checkGoogleVcode(HomeCheckGoogleVcodeRequest $request)
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->logAuth($request, '登录失败次数过多，请稍后再试', ['type' => 'too_many_attempts']);
            return $this->error("登录失败次数过多，请稍后再试");
        }
        $data = $request->only(['username', 'password', 'google_2fa_code']);
        $user = User::where('username', $data['username'])->first(['id', 'lock_user', 'status', 'google_two_fa_secret', 'google_two_fa_enable', 'password', 'username', 'name', 'self_add_bank', 'action_delete', 'acquisition_status', 'is_agent', 'deposit_notice', 'transfer_notice', 'auto_refresh', 'action_limit_card']);

        if (!$user) {
            $this->logAuth($request, "操作错误：用户不存在", ['type' => 'user_not_found']);
            return $this->error("操作错误");
        }

        if (!$this->consumeLoginStage($request, $user)) {
            $this->logAuth($request, "请先完成图形验证码校验", ['type' => 'login_stage_missing', 'user_id' => $user->id], $user);
            return $this->error('请先完成图形验证码校验');
        }

        if ((int)$user->status === 0) {
            $this->logAuth($request, "您的账号已禁用，请联系客服", ['type' => 'disabled', 'user_id' => $user->id], $user);
            return $this->error('您的账号已禁用，请联系客服');
        }

        // Google 验证
        if ($user->google_two_fa_enable == 2) {
            $google = new Google2FA();
            $google_verify_result = $google->verifyKey($user->google_two_fa_secret, $data['google_2fa_code']);
            if (!$google_verify_result) {
                $this->incrementLoginAttempts($request);
                $this->logAuth($request, "谷歌验证码不正确", ['type' => '2fa_wrong', 'user_id' => $user->id], $user);
                return $this->error('谷歌验证码不正确');
            }
        } else {
            if ($data['google_2fa_code'] != '000000') {
                $this->incrementLoginAttempts($request);
                $this->logAuth($request, "谷歌验证码不正确(期望000000)", ['type' => '2fa_wrong_default', 'user_id' => $user->id], $user);
                return $this->error('谷歌验证码不正确');
            }
        }

        if (!Hash::check($data['password'], $user->password)) {
            $this->incrementLoginAttempts($request);
            $this->logAuth($request, "用户名或密码不正确", ['type' => 'password_wrong'], $user);
            bob_send_system_user_login_exception_notice((string) $request->input('username', ''), 'user');
            return $this->error('用户名或密码不正确');
        }

        $this->clearLoginAttempts($request);

        DB::table("personal_access_tokens")->where('tokenable_id', $user->id)->delete();
        User::where('id', $user->id)->update(['last_login_time' => date('Y-m-d H:i:s', time()), 'last_login_ip' => bob_ip()]);
        $this->data['token'] = $user->createToken('user')->plainTextToken;
        $this->data['mtoken'] = bob_lock($this->data['token']);
        $this->data['username'] = bob_lock($user->username);
        $this->data['name'] = bob_lock($user->name);
        $this->data['userid'] = bob_lock($user->id);
        $this->data['self_add_bank'] = bob_lock($user->self_add_bank);
        $this->data['action_delete'] = bob_lock($user->action_delete);
        $this->data['status'] = bob_lock($user->acquisition_status);
        $this->data['agent'] = bob_lock($user->is_agent);
        $this->data['deposit_notice'] = bob_lock($user->deposit_notice);
        $this->data['transfer_notice'] = bob_lock($user->transfer_notice);
        $this->data['auto_refresh'] = bob_lock($user->auto_refresh);
        $this->data['action_limit_card'] = bob_lock($user->action_limit_card);
        $this->data['default_voice'] = asset("voice/default.mp3");
        $this->logAuth($request, "登录成功", ['type' => 'login_ok', 'user_id' => $user->id], $user);
        return $this->success('success', $this->data);
    }


    public function checkLogin(HomeCheckLoginRequest $request)
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->logAuth($request, '登录失败次数过多，请稍后再试', ['type' => 'too_many_attempts']);
            return $this->error("登录失败次数过多，请稍后再试");
        }
        $data = $request->only(['username', 'password']);
        $user = User::where('username', $data['username'])->first(['id', 'lock_user', 'status', 'google_two_fa_secret', 'google_two_fa_enable', 'password', 'username', 'name', 'self_add_bank', 'action_delete', 'acquisition_status', 'is_agent', 'deposit_notice', 'transfer_notice', 'auto_refresh', 'action_limit_card']);
        if (!$user) {
            $this->logAuth($request, "操作错误：用户不存在", ['type' => 'user_not_found']);
            return $this->error("操作错误");
        }
        if ($user->status == 0) {
            $this->logAuth($request, "您的账号已禁用，请联系客服", ['type' => 'disabled', 'user_id' => $user->id], $user);
            return $this->error('您的账号已禁用，请联系客服');
        }
        if (!Hash::check($data['password'], $user->password)) {
            $this->incrementLoginAttempts($request);
            $this->logAuth($request, "用户名或密码不正确", ['type' => 'password_wrong', 'user_id' => $user->id], $user);
            bob_send_system_user_login_exception_notice((string) $request->input('username', ''), 'user_agent');
            return $this->error('用户名或密码不正确');
        }

        $this->clearLoginAttempts($request);
        $this->putLoginStage($request, $user);

        $this->data['bind'] = 0;
        if ($user->google_two_fa_enable == 2 && empty($user->google_two_fa_secret)) {
            $this->data['bind'] = 1;
            $baconService = new Bacon(new ImagickImageBackEnd());
            $google2fa = new \PragmaRX\Google2FAQRCode\Google2FA($baconService);
            $this->data['google_two_fa_secret'] = $google_two_fa_secret = (new Google2FA())->generateSecretKey(32);
            $this->data['url'] = $google2fa->getQRCodeInline(config('admin.name'), $user->username, $google_two_fa_secret);
            $user->google_two_fa_secret = $google_two_fa_secret;
            $user->save();
            $this->logAuth($request, "需要绑定谷歌验证", ['type' => 'need_bind_2fa', 'user_id' => $user->id], $user);
        }else{
            $this->logAuth($request, "登录第一步校验成功", ['type' => 'password_ok', 'user_id' => $user->id], $user);
        }
        return $this->success('success', $this->data);
    }

    private function putLoginStage(Request $request, User $user): void
    {
        Cache::put($this->loginStageKey($request), [
            'user_id' => (int)$user->id,
            'username' => $this->loginStageUsername($request),
            'password_hash' => (string)$user->password,
            'created_at' => time(),
        ], self::LOGIN_STAGE_TTL_SECONDS);
    }

    private function consumeLoginStage(Request $request, User $user): bool
    {
        $key = $this->loginStageKey($request);
        $stage = Cache::pull($key);
        if (!is_array($stage)) {
            return false;
        }

        return (int)($stage['user_id'] ?? 0) === (int)$user->id
            && hash_equals((string)($stage['username'] ?? ''), $this->loginStageUsername($request))
            && hash_equals((string)($stage['password_hash'] ?? ''), (string)$user->password);
    }

    private function loginStageKey(Request $request): string
    {
        return implode(':', [
            $this->throttlePrefix(),
            'captcha_passed',
            hash('sha256', implode('|', [
                $this->loginStageUsername($request),
                (string)$request->ip(),
                (string)$request->userAgent(),
            ])),
        ]);
    }

    private function loginStageUsername(Request $request): string
    {
        return strtolower(trim((string)$request->input($this->username(), '')));
    }

    public function getCaptcha(Captcha $captcha, string $config = 'default')
    {
        $this->data = $captcha->create($config, true);
        return $this->success('', $this->data);
    }
}

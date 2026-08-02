<?php

namespace App\Traits;

use App\Models\IpBlacklist;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

trait ThrottlesLogins
{
    protected function hasLoginIpBlacklist(Request $request): bool
    {
        $ip = (string) bob_ip();
        if ($ip === '') {
            return false;
        }

        $type = $this->loginBlacklistType();

        $result = IpBlacklist::query()
            ->where('ip', $ip)
            ->whereIn('type', ['all', $type])
            ->where('status', 1)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
            ->exists();

        $this->writeLoginDebugLog('hasLoginIpBlacklist', [
            'prefix' => $this->throttlePrefix(),
            'username' => $this->throttleUser($request),
            'ip' => $ip,
            'type' => $type,
            'matched' => $result,
        ]);

        return $result;
    }

    protected function loginBlacklistType(): string
    {
        return match ($this->throttlePrefix()) {
            'admin' => 'system',
            'merchant_admin' => 'merchant',
            'agent_admin' => 'agent',
            'user_api_v2' => 'user',
            default => 'all',
        };
    }

    /**
     * 是否处于锁定期（按 lockKey）
     */
    public function hasTooManyLoginAttempts(Request $request): bool
    {
        $ipBlacklisted = $this->hasLoginIpBlacklist($request);
        $lockKey = $this->lockKey($request);
        $tooManyAttempts = $this->limiter()->tooManyAttempts($lockKey, 1);

        $this->writeLoginDebugLog('hasTooManyLoginAttempts', [
            'prefix' => $this->throttlePrefix(),
            'username' => $this->throttleUser($request),
            'ip' => (string) bob_ip(),
            'lock_key' => $lockKey,
            'total_key' => $this->totalKey($request),
            'risk_key' => $this->loginRiskKey($request),
            'ip_blacklisted' => $ipBlacklisted,
            'too_many_attempts' => $tooManyAttempts,
            'total_attempts' => $this->limiter()->attempts($this->totalKey($request)),
            'risk_data' => Cache::get($this->loginRiskKey($request)),
        ]);

        return $ipBlacklisted || $tooManyAttempts;
    }

    /**
     * 还需要等待多少秒
     */
    public function availableIn(Request $request): int
    {
        return $this->limiter()->availableIn($this->lockKey($request));
    }

    /**
     * 登录失败：累计总失败次数 + 按阶段设置锁
     */
    public function incrementLoginAttempts(Request $request): void
    {
        // 1) 先累计总失败次数（窗口：24小时，防无限累计）
        $totalKey = $this->totalKey($request);
        $this->limiter()->hit($totalKey, $this->totalWindowMinutes() * 60);

        $total = $this->limiter()->attempts($totalKey);

        // 2) 根据总失败次数计算本次要锁多久
        $minutes = $this->lockMinutesByTotalAttempts($total);

        // 3) 达到阈值才设置 lock key（1 次命中即锁）
        if ($minutes > 0) {
            $this->limiter()->hit($this->lockKey($request), $minutes * 60);
        }

        $this->writeLoginDebugLog('incrementLoginAttempts', [
            'prefix' => $this->throttlePrefix(),
            'username' => $this->throttleUser($request),
            'ip' => (string) bob_ip(),
            'total_key' => $totalKey,
            'lock_key' => $this->lockKey($request),
            'total_attempts' => $total,
            'lock_minutes' => $minutes,
            'lock_available_in' => $this->limiter()->availableIn($this->lockKey($request)),
        ]);
    }

    /**
     * 登录成功：清空总失败次数 + 锁
     */
    public function clearLoginAttempts(Request $request): void
    {
        $this->writeLoginDebugLog('clearLoginAttempts', [
            'prefix' => $this->throttlePrefix(),
            'username' => $this->throttleUser($request),
            'ip' => (string) bob_ip(),
            'total_key' => $this->totalKey($request),
            'lock_key' => $this->lockKey($request),
        ]);

        $this->limiter()->clear($this->totalKey($request));
        $this->limiter()->clear($this->lockKey($request));
    }


    public function recordLoginRisk(Request $request): void
    {
        $user = $this->throttleUser($request);
        if ($user === '') {
            return;
        }

        $key = $this->loginRiskKey($request);
        $data = Cache::get($key, [
            'fail_count' => 0,
            'ips' => [],
            'last_fail_at' => null,
        ]);

        $ip = (string) bob_ip();
        $data['fail_count'] = intval($data['fail_count'] ?? 0) + 1;
        if ($ip !== '') {
            $ips = is_array($data['ips'] ?? null) ? $data['ips'] : [];
            $ips[$ip] = time();
            $data['ips'] = $ips;
        }
        $data['last_fail_at'] = date('Y-m-d H:i:s');

        Cache::put($key, $data, now()->addMinutes($this->loginRiskWindowMinutes()));

        $this->writeLoginDebugLog('recordLoginRisk', [
            'prefix' => $this->throttlePrefix(),
            'username' => $user,
            'ip' => $ip,
            'risk_key' => $key,
            'risk_data' => $data,
        ]);
    }

    public function hasLoginRisk(Request $request): bool
    {
        $data = Cache::get($this->loginRiskKey($request), []);
        if (empty($data) || !is_array($data)) {
            return false;
        }

        $failCount = intval($data['fail_count'] ?? 0);
        $ips = is_array($data['ips'] ?? null) ? array_keys($data['ips']) : [];

        return $failCount >= $this->loginRiskFailureThreshold()
            && count($ips) >= $this->loginRiskIpThreshold();
    }

    public function clearLoginRisk(Request $request): void
    {
        $this->writeLoginDebugLog('clearLoginRisk', [
            'prefix' => $this->throttlePrefix(),
            'username' => $this->throttleUser($request),
            'risk_key' => $this->loginRiskKey($request),
        ]);

        Cache::forget($this->loginRiskKey($request));
    }

    public function unlockByUsername(string $username): void
    {
        $user = $this->normalizeUser($username);
        if ($user === '') {
            return;
        }

        $prefix = $this->throttlePrefix();

        $this->writeLoginDebugLog('unlockByUsername', [
            'prefix' => $prefix,
            'username' => $user,
            'total_key' => "{$prefix}:total:{$user}",
            'lock_key' => "{$prefix}:lock:{$user}",
            'risk_key' => "{$prefix}:risk:{$user}",
            'total_attempts_before' => $this->limiter()->attempts("{$prefix}:total:{$user}"),
            'lock_available_in_before' => $this->limiter()->availableIn("{$prefix}:lock:{$user}"),
            'risk_data_before' => Cache::get("{$prefix}:risk:{$user}"),
        ]);

        $this->limiter()->clear("{$prefix}:total:{$user}");
        $this->limiter()->clear("{$prefix}:lock:{$user}");
        Cache::forget("{$prefix}:risk:{$user}");

        $this->writeLoginDebugLog('unlockByUsername.cleared', [
            'prefix' => $prefix,
            'username' => $user,
            'total_attempts_after' => $this->limiter()->attempts("{$prefix}:total:{$user}"),
            'lock_available_in_after' => $this->limiter()->availableIn("{$prefix}:lock:{$user}"),
            'risk_data_after' => Cache::get("{$prefix}:risk:{$user}"),
        ]);
    }

    protected function loginDebugLogEnabled(): bool
    {
        return (bool) config('default.login_debug_log', false);
    }

    protected function writeLoginDebugLog(string $scene, array $context = []): void
    {
        if (! $this->loginDebugLogEnabled()) {
            return;
        }

        Log::info('login_debug.' . $scene, $context);
    }

    protected function normalizeUser(string $username): string
    {
        return Str::lower(trim($username));
    }

    /**
     * 生成用户名
     */
    protected function throttleUser(Request $request): string
    {
        return Str::lower((string)$request->input($this->username(), 'login'));
    }

    /**
     * 总失败次数 key（累计用）
     */
    protected function totalKey(Request $request): string
    {
        return implode(':', [
            $this->throttlePrefix(),
            'total',
            $this->throttleUser($request),
        ]);
    }

    /**
     * 锁 key（锁定用）
     */
    protected function lockKey(Request $request): string
    {
        return implode(':', [
            $this->throttlePrefix(),
            'lock',
            $this->throttleUser($request),
        ]);
    }


    protected function loginRiskKey(Request $request): string
    {
        return implode(':', [
            $this->throttlePrefix(),
            'risk',
            $this->throttleUser($request),
        ]);
    }

    protected function loginRiskWindowMinutes(): int
    {
        return 30;
    }

    protected function loginRiskFailureThreshold(): int
    {
        return 5;
    }

    protected function loginRiskIpThreshold(): int
    {
        return 1;
    }

    /**
     * key 前缀（不同端区分）
     */
    protected function throttlePrefix(): string
    {
        return 'login';
    }

    /**
     * 总失败次数统计窗口（分钟）
     * 比如 24 小时：用户隔天重新来不会继承昨天错误次数
     */
    protected function totalWindowMinutes(): int
    {
        return 1440;
    }

    /**
     * 阶梯锁定规则（你要的逻辑就在这里）
     * 返回 0 表示不锁
     */
    protected function lockMinutesByTotalAttempts(int $total): int
    {
        // 第3次起锁10分钟；第10次起锁30分钟；第20次锁120分钟，之后继续升级。
        return match (true) {
            $total < 3 => 0,
            $total < 10 => 10,
            $total < 20 => 30,
            $total === 20 => 120,
            default => 1440,
        };
    }

    /**
     * RateLimiter 实例
     */
    protected function limiter(): RateLimiter
    {
        return app(RateLimiter::class);
    }

    /**
     * Controller 必须实现
     */
    abstract public function username();
}

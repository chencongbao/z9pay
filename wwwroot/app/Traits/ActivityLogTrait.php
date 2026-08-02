<?php

namespace App\Traits;

use App\Services\Common\ActivityLogSensitiveDataService;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

trait ActivityLogTrait
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept([
                'appkey',
                'appsecret',
                'password',
                'remember_token',
                'google_two_fa_secret',
                'google_two_fa_code',
                'token',
                'created_at',
                'updated_at',
                'deleted_at'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName($this->resolveLogName());
    }

    protected function resolveLogName(): string
    {
        if (Auth::guard('admin')->check()) {
            return 'admin';
        }
        if (Auth::guard('merchant-admin')->check()) {
            return 'merchant';
        }
        if (Auth::guard('agent-admin')->check()) {
            return 'agent';
        }
        if (Auth::guard('sanctum')->check()) {
            return 'user';
        }

        return 'admin';
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $req = request();
        $activity->log_type = $this->resolveLogTypeFromRequest($req);
        $activity->ip = bob_ip();
        $activity->method = $req->method();
        $activity->path = '/' . ltrim($req->path(), '/');
        $activity->user_agent = substr((string)$req->userAgent(), 0, 1000);
        $activity->request_input = $this->filterSensitiveLogData($req->all());

        $props = $this->filterSensitiveLogData($this->normalizeProperties($activity->properties));
        // 自动补齐 action（手工已传则不覆盖）
        if (!array_key_exists('action', $props) || empty($props['action'])) {
            $props['action'] = $this->resolveActionKeyFromRequest($req, $eventName);
        }
        if (!array_key_exists('action_method', $props) || $props['action_method'] === null) {
            $props['action_method'] = match ($eventName) {
                'created' => 'POST',
                'updated' => 'PUT',
                'deleted' => 'DELETE',
                'restored' => 'PUT',
                default => null,
            };
        }
        $activity->properties = $props;

        if (empty($activity->causer_id)) {
            $causer = $this->resolveCauser();
            if ($causer) {
                $activity->causer()->associate($causer);
            }
        }

        if (empty($activity->subject_id)) {
            $activity->subject()->associate($this);
        }

        if (!$activity->description || $activity->description === $eventName) {
            $activity->description = $this->buildActivityDescription($eventName);
        }
    }

    protected function resolveCauser()
    {
        if (Auth::guard('admin')->check()) {
            return Auth::guard('admin')->user();
        }
        if (Auth::guard('merchant-admin')->check()) {
            return Auth::guard('merchant-admin')->user();
        }
        if (Auth::guard('agent-admin')->check()) {
            return Auth::guard('agent-admin')->user();
        }
        if (Auth::guard('sanctum')->check()) {
            return Auth::guard('sanctum')->user();
        }

        return null;
    }

    private function normalizeProperties($properties): array
    {
        if ($properties instanceof \Illuminate\Support\Collection) {
            return $properties->toArray();
        }
        if (is_object($properties) && method_exists($properties, 'toArray')) {
            return (array)$properties->toArray();
        }
        if (is_string($properties)) {
            $decoded = json_decode($properties, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        if (is_array($properties)) {
            return $properties;
        }

        return [];
    }

    private function filterSensitiveLogData(array $data): array
    {
        $changed = false;

        return app(ActivityLogSensitiveDataService::class)->sanitizeArray($data, true, $changed);
    }

    private function buildActivityDescription(string $eventName): string
    {
        $verbMap = [
            'created' => '新增',
            'updated' => '修改',
            'deleted' => '删除',
            'restored' => '恢复',
        ];
        $verb = $verbMap[$eventName] ?? '操作';

        $type = class_basename($this);
        $labels = (array)config('system-log.subject_labels', []);
        $label = $labels[$type] ?? $type;
        $id = $this->getKey();
        return $id ? ($verb . ' ' . $label . '#' . $id) : ($verb . ' ' . $label);
    }

    private function resolveLogTypeFromRequest($request): string
    {
        if (!$request || !method_exists($request, 'path')) {
            return 'operation';
        }

        $routeName = null;
        if (method_exists($request, 'route')) {
            $routeName = $request->route()?->getName();
        }
        if (is_string($routeName) && in_array($routeName, ['api.v2.check-login', 'api.v2.check-google-vcode'], true)) {
            return 'login';
        }

        $path = trim((string)$request->path(), '/');

        if (in_array($path, ['api/v2/checkLogin', 'api/v2/checkGoogleVcode'], true)) {
            return 'login';
        }

        $prefixes = array_filter([
            trim((string)config('admin.route.prefix', 'admin'), '/'),
            trim((string)config('merchant-admin.route.prefix', 'merchant-admin'), '/'),
            trim((string)config('agent-admin.route.prefix', 'agent-admin'), '/'),
        ]);

        foreach ($prefixes as $prefix) {
            if ($path === $prefix . '/auth/login' || $path === $prefix . '/auth/verify') {
                return 'login';
            }
        }

        return 'operation';
    }

    private function resolveActionKeyFromRequest($request, string $eventName): string
    {
        if ($request && method_exists($request, 'route')) {
            $route = $request->route();
            $routeName = $route?->getName();
            if (is_string($routeName) && $routeName !== '') {
                return $this->resolveActionByModel($routeName);
            }
        }

        return strtolower(class_basename($this)) . '.' . $eventName;
    }

    private function resolveActionByModel(string $routeName): string
    {
        $model = class_basename($this);

        if (str_starts_with($routeName, 'dcat.admin.merchant.users.')) {
            $op = (string)str($routeName)->afterLast('.');
            if ($model === 'MerchantInfo') {
                return 'dcat.admin.merchant.info.' . $op;
            }
            if ($model === 'MerchantUser') {
                return 'dcat.admin.merchant.users.' . $op;
            }
        }
        return $routeName;
    }
}

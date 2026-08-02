<?php

namespace App\Services\Common;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Arrayable;
use Spatie\Activitylog\Traits\LogsActivity;

class SystemLogService
{
    /**
     * 自动系统日志（HTTP 场景）
     */
    public function excute(Request $request, string $appType = 'admin', ?string $remark = null, string $logType = 'operation'): void
    {
        if (!config('system-log.enabled')) {
            return;
        }

        $app = config("system-log.apps.$appType");
        if (!$app) {
            return;
        }

        // 只记录写操作
        if (config('system-log.only_write_methods', true)) {
            if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                return;
            }
        }

        // 忽略路径
        if ($this->isIgnored($request)) {
            return;
        }

        $guard = $app['guard'] ?? null;
        $user = $guard ? auth()->guard($guard)->user() : Auth::user();

        $action = $this->resolveAction($request);
        $safeInput = $this->safeInput($request->all());

        $properties = [
            'action' => $action['key'],
        ];

        if ($route = $request->route()) {
            $properties['route_name'] = $route->getName();
            $properties['route_params'] = $route->parameters() ?? [];
        }

        $subject = $this->resolveSubject($request);

        if ($this->shouldSkipByModelActivity($subject)) {
            return;
        }

        $desc = $this->buildDescription($remark, $action['text'], $subject);

        if ($subject instanceof Model) {
            $properties['subject'] = [
                'type' => class_basename($subject),
                'id' => $subject->getKey(),
            ];
        }

        $activity = activity($app['log_name'] ?? $appType)
            ->withProperties($properties)
            ->tap(fn ($activity) => $this->fillActivityMeta(
                $activity,
                $request,
                $logType,
                $safeInput
            ));

        if ($user) {
            $activity->causedBy($user);
        }

        if ($subject instanceof Model) {
            $activity->performedOn($subject);
        }

        $activity->log($desc);
    }

    /**
     * 手动写入系统日志（非 HTTP 也可）
     *
     * @param string $appType  对应 config('system-log.apps')
     * @param string $actionKey 行为 key（机器码）
     * @param string $text     行为描述（中文行文）
     * @param Model|null $subject 关联模型
     * @param Model|null $user    操作人（可为空）
     * @param array $properties   额外属性（会合并 action/route 等）
     * @param string|null $remark 备注（优先显示）
     * @param array|null $requestInput 请求参数（可手动传）
     * @param string|null $ip
     * @param string|null $method
     * @param string|null $path
     * @param string|null $userAgent
     */
    public function manual(
        string $appType,
        string $actionKey,
        string $text,
        ?Model $subject = null,
        ?Model $user = null,
        array $properties = [],
        ?string $remark = null,
        ?array $requestInput = null,
        ?string $ip = null,
        ?string $method = null,
        ?string $path = null,
        ?string $userAgent = null,
        string $logType = 'operation'
    ): void {
        if (!config('system-log.enabled')) {
            return;
        }

        $app = config("system-log.apps.$appType");
        if (!$app) {
            return;
        }

        if (!$user) {
            $guard = $app['guard'] ?? null;
            $user = $guard ? auth()->guard($guard)->user() : Auth::user();
        }

        $safeInput = $this->safeInput($requestInput ?? []);
        $desc = $this->buildDescription($remark, $text, $subject);

        $baseProperties = [
            'action' => $actionKey,
        ];

        if ($subject instanceof Model) {
            $baseProperties['subject'] = [
                'type' => class_basename($subject),
                'id' => $subject->getKey(),
            ];
        }

        $properties = $this->safeLogData(array_merge($baseProperties, $properties));

        $activity = activity($app['log_name'] ?? $appType)
            ->withProperties($properties)
            ->tap(fn ($activity) => $this->fillActivityMeta(
                $activity,
                request(),
                $logType,
                $safeInput,
                $ip,
                $method,
                $path,
                $userAgent
            ));

        if ($user) {
            $activity->causedBy($user);
        }

        if ($subject instanceof Model) {
            $activity->performedOn($subject);
        }

        $activity->log($desc);
    }

    /**
     * 统一动作日志（简化调用）
     */
    public function logAction(
        string $actionKey,
        string $text,
        ?Model $subject = null,
        array|Collection|Arrayable|\JsonSerializable $properties = [],
        ?string $remark = null,
        string $logType = 'operation',
        ?string $actionMethod = null,
        string $appType = 'admin',
        $user = null,
        ?Request $request = null
    ): void {
        $req = $request ?: request();

        $properties = $this->normalizeActionProperties($properties);

        $method = $req->method();
        if ($actionMethod) {
            $properties['action_method'] = $actionMethod;
            $method = $actionMethod;
        } else {
            $guessed = $this->guessActionMethod($actionKey);
            if ($guessed) {
                $properties['action_method'] = $guessed;
                $method = $guessed;
            }
        }

        $this->manual(
            appType: $appType,
            actionKey: $actionKey,
            text: $text,
            subject: $subject,
            user: $user,
            properties: $properties,
            remark: $remark,
            requestInput: $req->all(),
            ip: bob_ip(),
            method: $method,
            path: '/' . ltrim($req->path(), '/'),
            userAgent: $req->userAgent(),
            logType: $logType
        );
    }

    private function guessActionMethod(string $actionKey): ?string
    {
        if ($actionKey === '') {
            return null;
        }
        $suffix = Str::afterLast($actionKey, '.');
        $map = [
            'store' => 'POST',
            'create' => 'POST',
            'update' => 'PUT',
            'edit' => 'PUT',
            'destroy' => 'DELETE',
            'delete' => 'DELETE',
            'reset' => 'PUT',
            'unlock' => 'PUT',
            'white_ip' => 'PUT',
        ];
        return $map[$suffix] ?? null;
    }

    /**
     * 描述生成：动作 + 对象（若有）
     */
    private function buildDescription(?string $remark, string $actionText, ?Model $subject): string
    {
        if ($remark) {
            return $remark;
        }

        $subjectText = $this->formatSubjectText($subject);
        if ($subjectText) {
            $label = Str::before($subjectText, '#');
            if ($label !== '' && str_contains($actionText, $label)) {
                return trim(Str::replaceFirst($label, $subjectText, $actionText));
            }
            return trim($actionText . ' | ' . $subjectText);
        }

        return trim($actionText);
    }

    /**
     * 对象显示：优先 subject_labels，其次 resources
     */
    private function formatSubjectText(?Model $subject): ?string
    {
        if (!$subject instanceof Model) {
            return null;
        }

        $id = $subject->getKey();
        if (!$id) {
            return null;
        }

        $type = class_basename($subject);
        $labelMap = (array)config('system-log.subject_labels', []);
        if (isset($labelMap[$type])) {
            return $labelMap[$type] . '#' . $id;
        }

        $resources = (array)config('system-log.resources', []);
        $key = Str::kebab(Str::pluralStudly($type));
        $label = $resources[$key] ?? $type;

        return $label . '#' . $id;
    }

    /**
     * 统一写入通用请求字段
     */
    private function fillActivityMeta(
        $activity,
        Request $request,
        string $logType,
        array $safeInput,
        ?string $ip = null,
        ?string $method = null,
        ?string $path = null,
        ?string $userAgent = null
    ): void {
        $activity->log_type = $logType;
        $activity->ip = $ip ?? bob_ip();
        $activity->method = $method ?? $request->method();
        $activity->path = $path ?? '/' . ltrim($request->path(), '/');
        $activity->user_agent = substr((string)($userAgent ?? $request->userAgent()), 0, 1000);
        $activity->request_input = $safeInput;
    }

    private function normalizeActionProperties($properties): array
    {
        if ($properties instanceof Collection) {
            return $properties->toArray();
        }
        if ($properties instanceof Arrayable) {
            return $properties->toArray();
        }
        if ($properties instanceof \JsonSerializable) {
            $v = $properties->jsonSerialize();
            return is_array($v) ? $v : [];
        }
        if (is_array($properties)) {
            return $properties;
        }

        return [];
    }

    private function shouldSkipByModelActivity(?Model $subject): bool
    {
        if (!$subject instanceof Model) {
            return false;
        }
        $traits = class_uses_recursive($subject);
        return $traits && in_array(LogsActivity::class, $traits, true);
    }

    /**
     * 行为解析：返回 key + text
     */
    private function resolveAction(Request $request): array
    {
        $route = $request->route();
        $routeName = $route?->getName();

        $map = $this->flattenActionMap((array)config('system-log.actions', []));
        if ($routeName && isset($map[$routeName]) && is_string($map[$routeName])) {
            return [
                'key' => $this->normalizeRouteActionKey($routeName),
                'text' => (string)$map[$routeName],
            ];
        }

        if ($routeName && str_contains($routeName, '.')) {
            return $this->guessDcatAction($routeName);
        }

        return [
            'key' => strtolower($request->method()) . ':' . $request->path(),
            'text' => $request->method() . ' ' . $request->path(),
        ];
    }

    private function guessDcatAction(string $routeName): array
    {
        $parts = explode('.', $routeName);

        $action = array_pop($parts); // store
        $resource = array_pop($parts); // merchant-channels

        $verbs = [
            'index' => '查看列表',
            'show' => '查看',
            'store' => '新增',
            'update' => '修改',
            'destroy' => '删除',
            'edit' => '编辑',
            'create' => '创建页面',
        ];

        $verbText = $verbs[$action] ?? '操作';
        $resourceText = $this->resolveResourceText($resource);

        return [
            'key' => $this->normalizeRouteActionKey($routeName),
            'text' => trim($verbText . ' ' . $resourceText),
        ];
    }

    private function normalizeRouteActionKey(string $routeName): string
    {
        return $routeName;
    }

    /**
     * 资源名替换：merchant-channels => 商户渠道
     */
    private function resolveResourceText(?string $resource): string
    {
        if (!$resource) {
            return '资源';
        }

        $resources = config('system-log.resources', []);

        if (isset($resources[$resource])) {
            $val = $resources[$resource];
            if (is_string($val)) {
                return $val;
            }
            if (is_array($val) && isset($val['text'])) {
                return (string)$val['text'];
            }
        }

        return str_replace('-', ' ', $resource);
    }

    private function flattenActionMap(array $actions): array
    {
        $flat = [];
        foreach ($actions as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $flat[$key] = $value;
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $subKey => $subVal) {
                    if (is_string($subKey) && is_string($subVal)) {
                        $flat[$subKey] = $subVal;
                    }
                }
            }
        }
        return $flat;
    }

    /**
     * 路由 → 模型
     */
    private function resolveSubject(Request $request): ?Model
    {
        return $this->resolveSubjectByRouteMap($request);
    }

    private function resolveSubjectByRouteMap(Request $request): ?Model
    {
        $route = $request->route();
        if (!$route) {
            return null;
        }

        $routeName = $route->getName();
        $map = config('system-log.route_models', []);
        if (!$routeName || !isset($map[$routeName])) {
            return null;
        }

        $id = $route->parameter('id') ?? collect($route->parameters())
            ->first(fn ($param) => is_scalar($param));
        $modelClass = $map[$routeName];
        if (!class_exists($modelClass)) {
            return null;
        }
        return $id ? $modelClass::find($id) : app($modelClass);
    }

    /**
     * 输入脱敏
     */
    private function safeInput(array $input): array
    {
        $input = $this->safeLogData($input);

        $maxLen = (int)config('system-log.max_input_length', 0);
        if ($maxLen > 0) {
            array_walk_recursive($input, function (&$v) use ($maxLen) {
                if (is_string($v) && mb_strlen($v) > $maxLen) {
                    $v = mb_substr($v, 0, $maxLen) . '...';
                }
            });
        }

        return $input;
    }

    private function safeLogData(array $data): array
    {
        $changed = false;

        return app(ActivityLogSensitiveDataService::class)->sanitizeArray($data, true, $changed);
    }

    /**
     * 忽略路径
     */
    private function isIgnored(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        foreach (config('system-log.ignore_paths', []) as $pattern) {
            $pattern = ltrim((string)$pattern, '/');
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
            if (preg_match($regex, $path)) {
                return true;
            }
        }

        $routeName = $request->route()?->getName();
        if ($routeName) {
            foreach (config('system-log.ignore_route_names', []) as $pattern) {
                $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
                if (preg_match($regex, $routeName)) {
                    return true;
                }
            }
        }

        return false;
    }
}

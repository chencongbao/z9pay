<?php

namespace App\Admin\Controllers;

use App\Models\User;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\AgentUser;
use Illuminate\Support\Str;
use App\Models\MerchantUser;
use Dcat\Admin\Layout\Content;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use App\Services\Common\ActivityLogSensitiveDataService;
use Spatie\Activitylog\Models\Activity;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Models\Administrator as DcatAdministrator;

class OtherLogController extends AdminController
{
    protected $translation = 'operation-logs';

    protected array $merchantCauserCache = [];

    public function index(Content $content): Content
    {
        $fixedLogName = $this->resolveFixedLogName();
        $title = $this->resolveTitle('操作日志', $fixedLogName);

        return $content->title($title)
            ->description(trans('admin.list'))
            ->body($this->grid());
    }

    protected function grid(): Grid
    {
        $fixedLogName = $this->resolveFixedLogName();
        $currentAdmin = Admin::user();
        $appOptions = collect((array) config('system-log.apps', []))
            ->mapWithKeys(fn ($v, $k) => [$v['log_name'] ?? $k => $v['title'] ?? $k])
            ->toArray();
        $causerOptions = $this->buildCauserOptions($fixedLogName, (int) ($currentAdmin?->id ?? 0));
        $serviceActionOptions = $this->serviceActionOptions($fixedLogName);
        $self = $this;
        $query = Activity::query()
            ->select(['id', 'log_name', 'log_type', 'description', 'properties', 'causer_type', 'causer_id', 'subject_type', 'subject_id', 'request_input', 'method', 'ip', 'created_at'])
            ->with('causer');

        return Grid::make($query, function (Grid $grid) use ($appOptions, $fixedLogName, $currentAdmin, $self, $causerOptions, $serviceActionOptions) {
            $grid->model()->where('log_type', 'operation')->latest('id');
            if ($fixedLogName) {
                $grid->model()->where('log_name', $fixedLogName);
            }
            if (!$currentAdmin?->isAdministrator() && $fixedLogName === 'admin') {
                $grid->model()->where(function ($q) {
                    $q->whereNull('causer_id')->orWhere('causer_id', '<>', 1);
                });
            }

            $grid->column('id', 'ID')->sortable();
            $grid->column('causer_info', '操作者')->display(function () use ($self) {
                $name = (string) data_get($this->causer, 'name', '');
                $username = (string) data_get($this->causer, 'username', '');
                $label = $self->formatCauserName($name, $username);
                $label = $label !== '' ? $label : ($this->causer_id ? 'ID ' . $this->causer_id : '-');

                return $this->causer_id ? ($label . ' (#' . $this->causer_id . ')') : $label;
            });
            if ($fixedLogName === 'merchant') {
                $grid->column('merchant_affiliation', '所属商户')->display(function () use ($self) {
                    return $self->resolveMerchantAffiliationLabel((int) $this->causer_id);
                })->limit(60);
                $grid->column('merchant_account_type', '账号类型')->display(function () use ($self) {
                    return $self->resolveMerchantAccountTypeLabel((int) $this->causer_id);
                });
            }
            $grid->column('method', '动作')->display(function ($v) {
                $props = $this->properties;
                if (is_string($props)) {
                    $decoded = json_decode($props, true);
                    if (is_array($decoded)) {
                        $props = $decoded;
                    }
                }
                $actionMethod = data_get($props, 'action_method');
                $method = strtoupper((string) ($actionMethod ?: $v));
                $map = [
                    'GET' => '查看',
                    'POST' => '新增',
                    'PUT' => '修改',
                    'PATCH' => '修改',
                    'DELETE' => '删除',
                ];

                return $map[$method] ?? $method;
            });
            $grid->column('object', '对象')->display(function () use ($self) {
                $subject = data_get($this->properties, 'subject');
                if (!$subject && $this->subject_type && $this->subject_id) {
                    $subject = [
                        'type' => class_basename($this->subject_type),
                        'id' => $this->subject_id,
                    ];
                }
                $input = $this->request_input;

                return $self->formatObjectText($subject, $input);
            });
            $grid->column('description', '操作描述')->display(function ($value) use ($self) {
                $props = $self->normalizeProperties($this->properties ?? []);
                $action = (string) data_get($props, 'action', '');
                if ($action !== '') {
                    $mapped = $self->resolveActionTextByLogName($action, (string) $this->log_name);
                    if ($mapped !== null) {
                        return $mapped;
                    }
                }

                return app(ActivityLogSensitiveDataService::class)->sanitizeDescription((string)$value);
            })->limit(80);
            $grid->column('changes', '变更字段')->width(360)->display(function () use ($self) {
                $props = $this->properties ?? [];
                $old = data_get($props, 'old', []);
                $new = data_get($props, 'attributes', []);
                if (!is_array($old) || !is_array($new)) {
                    return '-';
                }

                $labelMap = config('system-log.change_labels_cn', []);
                if (!is_array($labelMap)) {
                    $labelMap = [];
                }
                $defaultLabelMap = $labelMap;
                $maskKeys = (array) config('system-log.mask_keys', []);
                $subjectType = data_get($props, 'subject.type') ?: $this->subject_type;
                if ($subjectType && isset($labelMap[$subjectType]) && is_array($labelMap[$subjectType])) {
                    $labelMap = array_merge($defaultLabelMap, $labelMap[$subjectType]);
                } elseif ($subjectType) {
                    $shortType = class_basename($subjectType);
                    if (isset($labelMap[$shortType]) && is_array($labelMap[$shortType])) {
                        $labelMap = array_merge($defaultLabelMap, $labelMap[$shortType]);
                    }
                }
                $maxFields = (int) config('system-log.change_max_fields', 8);
                $maxLen = (int) config('system-log.change_value_max_length', 80);

                $rows = [];
                foreach ($new as $k => $v) {
                    $ov = $old[$k] ?? null;
                    if ($ov !== $v) {
                        $label = array_key_exists($k, $labelMap) ? $labelMap[$k] : $k;
                        if (in_array($k, $maskKeys, true)) {
                            $rows[] = $label . ': ****** -> ******';
                        } else {
                            $rows[] = $label . ': ' . $self->formatChangeValue($ov, $maxLen) . ' -> ' . $self->formatChangeValue($v, $maxLen);
                        }
                    }
                    if (count($rows) >= $maxFields) {
                        break;
                    }
                }

                if (empty($rows)) {
                    return '-';
                }

                return implode('；', $rows);
            })->limit(120);
            $grid->column('request_input', '表单数据')->width(420)->display(function ($input) {
                if (empty($input)) {
                    return '-';
                }
                $data = is_string($input) ? json_decode($input, true) : $input;
                if (empty($data) || !is_array($data)) {
                    return '-';
                }
                $changed = false;
                $data = app(ActivityLogSensitiveDataService::class)->sanitizeArray($data, false, $changed);
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $html = '<pre class="dump" style="max-width: 500px">' . e($json ?: '') . '</pre>';

                return new HtmlString($html);
            });
            $grid->column('ip', 'IP')->filterByValue();
            $grid->column('created_at', '操作时间')->sortable()->display(function () {
                return $this->created_at->format('Y-m-d H:i:s');
            });

            $grid->disableCreateButton();
            $grid->disableQuickEditButton();
            $grid->disableEditButton();
            $grid->disableViewButton();
            $grid->showColumnSelector();
            $grid->setActionClass(Grid\Displayers\Actions::class);
            $grid->disableActions();

            $grid->filter(function (Grid\Filter $filter) use ($appOptions, $fixedLogName, $causerOptions, $serviceActionOptions) {
                $filter->expand();
                $filter->panel();
                if (!$fixedLogName) {
                    $filter->equal('log_name', '端类型')->select($appOptions)->width(3);
                }
                if (!empty($causerOptions)) {
                    $filter->equal('causer_id', '操作者')->select($causerOptions)->width(3);
                } else {
                    $filter->equal('causer_id', '操作者ID')->width(3);
                }
                $filter->where('action', function ($query) {
                    $value = (string) $this->input;
                    if ($value === '') {
                        return;
                    }
                    $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(properties, '$.action')) = ?", [$value]);
                }, '业务动作')->select($serviceActionOptions)->width(3);
                $filter->equal('subject_id', '对象ID')->width(3);
                $filter->like('description', '描述关键字')->width(3);
                $filter->equal('ip', '登录IP')->width(3);
                $filter->between('created_at', '登录时间')->datetime()->width(3);
            });
        });
    }

    private function resolveFixedLogName(): ?string
    {
        $logName = request()->route('log_name');
        if (!$logName) {
            $logName = request('log_name');
        }
        if (!$logName) {
            return null;
        }

        $allowed = collect((array) config('system-log.apps', []))
            ->map(fn ($v, $k) => $v['log_name'] ?? $k)
            ->values()
            ->all();

        return in_array($logName, $allowed, true) ? $logName : null;
    }

    private function resolveTitle(string $suffix, ?string $logName): string
    {
        if (!$logName) {
            return $suffix;
        }

        $apps = (array) config('system-log.apps', []);
        $title = collect($apps)
            ->mapWithKeys(fn ($v, $k) => [$v['log_name'] ?? $k => $v['title'] ?? $k])
            ->get($logName, $logName);

        return $title . ' ' . $suffix;
    }

    private function normalizeProperties($props): array
    {
        if ($props instanceof Collection) {
            return $props->toArray();
        }
        if (is_object($props) && method_exists($props, 'toArray')) {
            return (array) $props->toArray();
        }
        if (is_string($props)) {
            $decoded = json_decode($props, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return is_array($props) ? $props : [];
    }

    private function buildCauserOptions(?string $logName, int $currentAdminId): array
    {
        if (!$logName) {
            return [];
        }

        $limit = (int) config('system-log.causer_select_limit', 2000);
        $excludeSuper = $currentAdminId !== 1;

        if ($logName === 'admin') {
            return DcatAdministrator::query()
                ->select(['id', 'name', 'username'])
                ->when($excludeSuper, fn ($q) => $q->where('id', '<>', 1))
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->mapWithKeys(fn ($u) => [$u->id => $this->formatCauserOption($u)])
                ->toArray();
        }

        if ($logName === 'merchant') {
            return MerchantUser::query()
                ->select(['id', 'name', 'username'])
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->mapWithKeys(fn ($u) => [$u->id => $this->formatCauserOption($u)])
                ->toArray();
        }

        if ($logName === 'agent') {
            return AgentUser::query()
                ->select(['id', 'name', 'username'])
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->mapWithKeys(fn ($u) => [$u->id => $this->formatCauserOption($u)])
                ->toArray();
        }

        if ($logName === 'user') {
            return User::query()
                ->select(['id', 'username', 'name'])
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->mapWithKeys(fn ($u) => [$u->id => $this->formatCauserOption($u)])
                ->toArray();
        }

        return [];
    }

    private function resolveMerchantAffiliationLabel(int $causerId): string
    {
        $merchantUser = $this->resolveMerchantCauser($causerId);
        $merchantName = (string) data_get($merchantUser, 'merchant_info.bname', '');

        return $merchantName !== '' ? $merchantName : '-';
    }

    private function resolveMerchantAccountTypeLabel(int $causerId): string
    {
        $merchantUser = $this->resolveMerchantCauser($causerId);
        if (!$merchantUser) {
            return '-';
        }

        return (int) ($merchantUser->pid ?? 0) > 0 ? '子账号' : '主账号';
    }

    private function resolveMerchantCauser(int $causerId): ?MerchantUser
    {
        if ($causerId <= 0) {
            return null;
        }

        if (!array_key_exists($causerId, $this->merchantCauserCache)) {
            $this->merchantCauserCache[$causerId] = MerchantUser::query()
                ->select(['id', 'pid'])
                ->with(['merchant_info' => function ($query) {
                    $query->select(['merchant_user_id', 'currency_id', 'name', 'coder']);
                }])
                ->find($causerId);
        }

        return $this->merchantCauserCache[$causerId];
    }

    private function formatObjectText($subject, $input): string
    {
        $subjectLabelMap = (array) config('system-log.subject_labels', []);
        if (is_array($subject)) {
            $type = $subject['type'] ?? null;
            $id = $subject['id'] ?? null;
            if ($type && $id) {
                $label = $subjectLabelMap[$type] ?? $this->resolveLabelByType($type);
                return $label . '#' . $id;
            }
        }

        $data = is_string($input) ? json_decode($input, true) : $input;
        if (!is_array($data)) {
            return '-';
        }

        $keys = (array) config('system-log.object_keys', ['merchant_id', 'agent_id', 'user_id', 'order_id', 'order_no', 'channel_id', 'id']);
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $val = $data[$key];
            if ($val === '' || $val === null) {
                continue;
            }
            return $key . ':' . $val;
        }

        return '-';
    }

    private function resolveLabelByType(string $type): string
    {
        $resources = (array) config('system-log.resources', []);
        $key = Str::kebab(Str::pluralStudly($type));

        return $resources[$key] ?? $type;
    }

    private function formatChangeValue($value, int $maxLen): string
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($value === null) {
            $value = 'null';
        } else {
            $value = (string) $value;
        }

        if ($maxLen > 0 && mb_strlen($value) > $maxLen) {
            $value = mb_substr($value, 0, $maxLen) . '...';
        }

        return $value;
    }

    private function serviceActionOptions(?string $logName = null): array
    {
        return $this->businessActionMap($logName);
    }

    private function businessActionMap(?string $logName = null): array
    {
        $actions = (array) config('system-log.actions', []);

        if ($this->isFlatActionMap($actions)) {
            return $this->normalizeActionMap($actions);
        }

        if ($logName && isset($actions[$logName]) && is_array($actions[$logName])) {
            return $this->normalizeActionMap((array) $actions[$logName]);
        }

        $merged = [];
        foreach ($actions as $group => $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($this->normalizeActionMap($items) as $key => $text) {
                $merged[$key] = $text;
            }
        }

        return $merged;
    }

    private function isFlatActionMap(array $actions): bool
    {
        foreach ($actions as $value) {
            if (is_array($value)) {
                return false;
            }
        }
        return true;
    }

    private function normalizeActionMap(array $items): array
    {
        $map = [];
        foreach ($items as $key => $text) {
            if (!is_string($key) || $key === '' || !is_string($text) || $text === '') {
                continue;
            }
            $map[$key] = $text;
        }

        return $map;
    }

    private function resolveActionTextByLogName(string $action, ?string $logName): ?string
    {
        $logName = is_string($logName) ? trim($logName) : '';
        if ($logName === '') {
            return null;
        }

        $actions = (array) config('system-log.actions', []);
        if ($this->isFlatActionMap($actions)) {
            $map = $this->normalizeActionMap($actions);

            return $map[$action] ?? null;
        }

        $group = $actions[$logName] ?? null;
        if (!is_array($group)) {
            return null;
        }

        $map = $this->normalizeActionMap($group);

        return $map[$action] ?? null;
    }

    private function formatCauserOption($user): string
    {
        $label = $this->formatCauserName((string) ($user->name ?? ''), (string) ($user->username ?? ''));
        $label = $label !== '' ? $label : ('ID ' . $user->id);

        return $label . ' (#' . $user->id . ')';
    }

    private function formatCauserName(string $name, string $username): string
    {
        if ($name !== '' && $username !== '') {
            return $name . '(' . $username . ')';
        }

        return $name !== '' ? $name : $username;
    }
}

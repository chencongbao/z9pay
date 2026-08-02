<?php

namespace App\Admin\Controllers;

use App\Models\User;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\AgentUser;
use App\Models\MerchantUser;
use Dcat\Admin\Layout\Content;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Models\Administrator as DcatAdministrator;

class LoginLogController extends AdminController
{
    protected $translation = 'operation-logs';

    protected array $merchantCauserCache = [];

    public function index(Content $content): Content
    {
        $fixedLogName = $this->resolveFixedLogName();
        $title = $this->resolveTitle('登录日志', $fixedLogName);

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
        $self = $this;
        $query = Activity::query()
            ->select(['id', 'log_name', 'log_type', 'description', 'causer_type', 'causer_id', 'ip', 'created_at'])
            ->with('causer');

        return Grid::make($query, function (Grid $grid) use ($appOptions, $fixedLogName, $causerOptions, $currentAdmin, $self) {
            $grid->model()->where('log_type', 'login')->latest('id');
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
            $grid->column('result', '结果')->display(function () {
                $desc = (string) ($this->description ?? '');
                if ($desc === '') {
                    return '-';
                }
                if (str_contains($desc, '成功')) {
                    return new HtmlString('<span class="badge badge-success">成功</span>');
                }
                if (str_contains($desc, '失败') || str_contains($desc, '错误') || str_contains($desc, '不正确') || str_contains($desc, '禁用')) {
                    return new HtmlString('<span class="badge badge-danger">失败</span>');
                }

                return new HtmlString('<span class="badge badge-info">其他</span>');
            });
            $grid->column('description', '结果/描述')->display(function ($value) {
                $text = (string) $value;
                if ($text === '') {
                    return $text;
                }

                return preg_replace('/(密码[:：])\\S+/u', '$1******', $text) ?? $text;
            })->limit(80);
            $grid->column('ip', '登录IP')->filterByValue();
            $grid->column('created_at', '登录时间')->sortable()->display(function () {
                return $this->created_at->format('Y-m-d H:i:s');
            });

            $grid->disableCreateButton();
            $grid->disableQuickEditButton();
            $grid->disableEditButton();
            $grid->disableViewButton();
            $grid->showColumnSelector();
            $grid->setActionClass(Grid\Displayers\Actions::class);
            $grid->disableActions();

            $grid->filter(function (Grid\Filter $filter) use ($appOptions, $fixedLogName, $causerOptions) {
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
                $filter->like('description', '描述关键字')->width(3);
                $filter->equal('ip', 'IP')->width(3);
                $filter->where('结果', function ($query) {
                    $value = $this->input;
                    if ($value === 'success') {
                        $query->where('description', 'like', '%成功%');
                    } elseif ($value === 'failed') {
                        $query->where(function ($q) {
                            $q->where('description', 'like', '%失败%')->orWhere('description', 'like', '%错误%')->orWhere('description', 'like', '%不正确%')->orWhere('description', 'like', '%禁用%');
                        });
                    }
                })->select(['success' => '成功', 'failed' => '失败'])->width(3);
                $filter->between('created_at')->datetime()->width(3);
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

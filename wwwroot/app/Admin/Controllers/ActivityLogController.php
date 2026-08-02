<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends CommonController
{
    protected function grid()
    {
        $buildSubjectUrl = function (string $type, string|int $id): ?string {
            return $this->buildSubjectUrl($type, $id);
        };
        $renderTimelineModal = function (string $modalId, string $type, string|int $sid): string {
            return $this->renderTimelineModal($modalId, $type, $sid);
        };

        $query = Activity::query()
            ->select(['id', 'log_name', 'event', 'description', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'properties', 'created_at'])
            ->with(['causer']);

        return Grid::make($query, function (Grid $grid) use ($buildSubjectUrl, $renderTimelineModal) {
            $grid->model()->latest('id');

            $grid->column('id')->sortable();
            $grid->column('log_name', '分组')->label('primary');
            $grid->column('event', '事件')->using([
                'created' => '创建',
                'updated' => '更新',
                'deleted' => '删除',
                'restored' => '还原',
            ])->dot([
                'created' => 'success',
                'updated' => 'warning',
                'deleted' => 'danger',
                'restored' => 'info',
            ], 'default')->sortable();
            $grid->column('description', '描述')->limit(40);

            $grid->column('subject', '对象')->display(function () use ($buildSubjectUrl) {
                $type = $this->subject_type;
                $id = $this->subject_id;
                if (!$type || !$id) {
                    return '-';
                }

                $url = $buildSubjectUrl($type, $id);
                $short = e(class_basename($type) . ' #' . $id);
                return new HtmlString($url ? '<a href="' . e($url) . '">' . $short . '</a>' : $short);
            });

            $grid->column('causer_id', '操作者ID')->sortable();
            $grid->column('causer.name', '操作者')->display(fn ($v) => $v ?: '-');

            $grid->column('properties', '上下文')->display(function ($props) {
                $ctx = data_get($props, '_context', []);
                $ip = data_get($ctx, 'ip');
                $ua = data_get($ctx, 'user_agent');
                $url = data_get($ctx, 'url');
                $rid = data_get($ctx, 'request_id');
                $shortUa = $ua ? mb_substr($ua, 0, 30) . (mb_strlen($ua) > 30 ? '…' : '') : null;

                $lines = [];
                $ip && $lines[] = 'IP：' . e($ip);
                $url && $lines[] = 'URL：' . e($url);
                $rid && $lines[] = 'ReqID：' . e($rid);
                $ua && $lines[] = 'UA：' . e($shortUa);

                return $lines ? new HtmlString(implode('<br>', $lines)) : '-';
            });

            $grid->column('changes', '变更')->display(function () {
                $props = $this->properties ?? [];
                $old = data_get($props, 'old', []);
                $new = data_get($props, 'attributes', []);

                $changed = [];
                foreach ($new as $k => $v) {
                    $ov = $old[$k] ?? null;
                    if ($ov !== $v) {
                        $changed[$k] = ['old' => $ov, 'new' => $v];
                    }
                }
                if (!$changed) {
                    return '-';
                }

                $count = count($changed);
                $tableRows = '';
                foreach ($changed as $k => $pair) {
                    $field = e($k);
                    $o = e(is_scalar($pair['old']) || is_null($pair['old']) ? var_export($pair['old'], true) : json_encode($pair['old'], JSON_UNESCAPED_UNICODE));
                    $n = e(is_scalar($pair['new']) || is_null($pair['new']) ? var_export($pair['new'], true) : json_encode($pair['new'], JSON_UNESCAPED_UNICODE));
                    $tableRows .= "<tr><td style='width:180px'><code>{$field}</code></td><td style='color:#999'>{$o}</td><td>{$n}</td></tr>";
                }

                $modalId = 'chg-' . $this->id;
                $modal = <<<HTML
<a href="javascript:void(0)" data-toggle="modal" data-target="#{$modalId}"><span class="badge badge-info">{$count} 项</span> 查看</a>
<div class="modal fade" id="{$modalId}" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">变更详情 #{$this->id}</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" style="overflow:auto;">
        <table class="table table-sm table-bordered">
          <thead><tr><th>字段</th><th>旧值</th><th>新值</th></tr></thead>
          <tbody>{$tableRows}</tbody>
        </table>
      </div>
    </div>
  </div>
</div>
HTML;
                return new HtmlString($modal);
            });

            $grid->column('timeline', '时间线')->display(function () use ($renderTimelineModal) {
                if (!$this->subject_type || !$this->subject_id) {
                    return '-';
                }

                $modalId = 'tl-' . $this->id;
                $timeline = <<<HTML
<a href="javascript:void(0)" data-toggle="modal" data-target="#{$modalId}">查看</a>
{$renderTimelineModal($modalId, $this->subject_type, $this->subject_id)}
HTML;
                return new HtmlString($timeline);
            });

            $grid->column('created_at', '时间')->sortable()->width(160);

            $grid->quickSearch('description', 'subject_id', 'causer_id')->placeholder('描述 / 对象ID / 操作者ID');

            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id')->width(3);
                $filter->like('description', '描述')->width(4);
                $filter->equal('log_name', '分组')->width(3);
                $filter->in('event', '事件')->multipleSelect([
                    'created' => '创建',
                    'updated' => '更新',
                    'deleted' => '删除',
                    'restored' => '还原',
                ])->width(5);
                $filter->like('subject_type', '对象类型')->width(4);
                $filter->equal('subject_id', '对象ID')->width(3);
                $filter->equal('causer_id', '操作者ID')->width(3);
                $filter->between('created_at', '时间')->datetime()->width(6);

                $filter->where('请求IP', function ($query) {
                    $kw = $this->input;
                    if ($kw) {
                        $query->where('properties', 'like', '%"ip":"' . $kw . '"%');
                    }
                })->width(3);

                $filter->where('RequestId', function ($query) {
                    $kw = $this->input;
                    if ($kw) {
                        $query->where('properties', 'like', '%"request_id":"' . $kw . '"%');
                    }
                })->width(3);

                $filter->where('变更包含字段', function ($query) {
                    $kw = $this->input;
                    if ($kw) {
                        $query->where('properties', 'like', '%"' . $kw . '"%');
                    }
                })->width(4);
            });

            $grid->showColumnSelector();
            $grid->paginate(20);
            $grid->disableCreateButton();
            $grid->disableActions();
        });
    }

    protected function buildSubjectUrl(string $subjectType, $subjectId): ?string
    {
        $map = [
            \App\Models\Order::class => 'orders',
            \App\Models\User::class => 'users',
        ];

        $uri = $map[$subjectType] ?? null;
        if (!$uri) {
            return null;
        }

        return admin_url("{$uri}/{$subjectId}");
    }

    protected function renderTimelineModal(string $modalId, string $subjectType, $subjectId): string
    {
        static $timelineCache = [];

        $cacheKey = $subjectType . ':' . $subjectId;
        if (!array_key_exists($cacheKey, $timelineCache)) {
            $timelineCache[$cacheKey] = Activity::query()
                ->select(['id', 'event', 'description', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'properties', 'created_at'])
                ->with(['causer'])
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        $items = $timelineCache[$cacheKey];

        if ($items->isEmpty()) {
            return <<<HTML
<div class="modal fade" id="{$modalId}" tabindex="-1" role="dialog"><div class="modal-dialog modal-lg">
  <div class="modal-content"><div class="modal-header"><h5 class="modal-title">时间线</h5>
    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body">无更多记录</div>
  </div></div></div>
HTML;
        }

        $rows = '';
        foreach ($items as $a) {
            $when = $a->created_at?->format('Y-m-d H:i:s');
            $event = e($a->event ?: '-');
            $desc = e($a->description ?: '-');
            $causer = e($a->causer?->name ?: ($a->causer_id ?: '-'));

            $props = $a->properties ?? [];
            $old = data_get($props, 'old', []);
            $new = data_get($props, 'attributes', []);
            $keys = array_unique(array_merge(array_keys((array)$old), array_keys((array)$new)));
            $badges = $keys ? implode(' ', array_map(fn ($k) => "<span class='badge badge-secondary' style='margin-right:4px'>" . e($k) . "</span>", $keys)) : '-';

            $rows .= <<<HTML
<tr>
  <td style="white-space:nowrap">{$when}</td>
  <td style="white-space:nowrap">{$event}</td>
  <td>{$desc}</td>
  <td style="white-space:nowrap">{$causer}</td>
  <td>{$badges}</td>
</tr>
HTML;
        }

        $subjectName = e(class_basename($subjectType));
        $subjectId = e($subjectId);
        return <<<HTML
<div class="modal fade" id="{$modalId}" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">对象时间线：{$subjectName} #{$subjectId}</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" style="overflow:auto;">
        <table class="table table-sm table-bordered">
          <thead>
            <tr>
              <th style="width:160px">时间</th>
              <th style="width:80px">事件</th>
              <th>描述</th>
              <th style="width:140px">操作者</th>
              <th>涉及字段</th>
            </tr>
          </thead>
          <tbody>{$rows}</tbody>
        </table>
      </div>
    </div>
  </div>
</div>
HTML;
    }
}

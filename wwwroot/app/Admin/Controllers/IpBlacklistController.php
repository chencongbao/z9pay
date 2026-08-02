<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\IpBlacklist;
use App\Admin\Actions\Grid\IpBlacklist\Unban;

class IpBlacklistController extends CommonController
{
    protected $title = 'IP黑名单';

    protected $disableDestroy = false;

    protected $disableCreate = true;

    protected $disableEdit = true;

    protected function grid(): Grid
    {
        $typeOptions = [
            'all' => '全部端',
            'system' => '系统端',
            'merchant' => '商户端',
            'agent' => '代理端',
            'user' => '金主端',
        ];
        $statusOptions = [
            1 => '启用',
            0 => '禁用',
        ];
        $statusDots = [
            1 => 'success',
            0 => 'danger',
        ];
        $query = IpBlacklist::query()->select(['id', 'ip', 'type', 'status', 'reason', 'remark', 'locked_at', 'expires_at', 'created_at']);

        return Grid::make($query, function (Grid $grid) use ($typeOptions, $statusOptions, $statusDots) {
            $canUnban = Admin::user()->can('ip-blacklist-unban');

            $grid->model()->orderByDesc('id');
            if (!request()->filled('status')) {
                $grid->model()->where('status', 1);
            }
            $grid->rows(function ($rows) {
                $rows->each(function ($row) {
                    if ((int) $row->model()->status === 0) {
                        $row->style('color:#8c8c8c;background-color:#f5f5f5;');
                    }
                });
            });

            $grid->column('id', '编号')->sortable();
            $grid->column('ip', 'IP');
            $grid->column('type', '类型')->using($typeOptions)->label();
            $grid->column('status', '状态')->using($statusOptions)->dot($statusDots);
            $grid->column('reason', '原因');
            $grid->column('remark', '备注')->limit(40);
            $grid->column('locked_at', '封禁时间');
            $grid->column('expires_at', '到期时间')->display(function ($value) {
                return $value ?: '永久';
            });
            $grid->column('created_at', '创建时间');

            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->showViewButton(false);
            $grid->actions(function (Grid\Displayers\Actions $actions) use ($canUnban) {
                $actions->disableEdit();
                $actions->disableView();
                $actions->disableDelete();
                if ($canUnban && (int) $actions->row->status === 1) {
                    $actions->append(new Unban());
                }
            });

            $grid->filter(function (Grid\Filter $filter) use ($typeOptions, $statusOptions) {
                $filter->expand();
                $filter->panel();
                $filter->equal('id', '编号')->width(3);
                $filter->equal('ip', 'IP')->width(3);
                $filter->equal('type', '类型')->select($typeOptions)->width(3);
                $filter->equal('status', '状态')->select($statusOptions)->default(1)->width(3);
            });
        });
    }

    protected function form(): Form
    {
        return Form::make(new IpBlacklist());
    }
}

<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use App\Models\UserBankActionLog;

class UserBankActionLogController extends Grid\LazyRenderable
{
    public function grid(): Grid
    {
        $userBankId = (int) request('user_bank_id');
        $query = UserBankActionLog::query()->select(['id', 'user_bank_id', 'type', 'name', 'action', 'remark', 'created_at'])->orderByDesc('id');
        if ($userBankId > 0) {
            $query->where('user_bank_id', $userBankId);
        }

        return Grid::make($query, function (Grid $grid) {
            $grid->column('id')->sortable();
            $grid->column('type', '操作角色')->using([1 => '金主', 2 => '系统管理员', 3 => '金主代理'])->dot(bob_colors());
            $grid->column('name', '操作人');
            $grid->column('action', '操作类型')->display(function ($value) {
                return config('default.user_bank_action_type')[(int) $value] ?? '';
            });
            $grid->column('remark', '备注')->display(function ($value) {
                return self::formatRemark($value);
            });
            $grid->column('created_at', '操作时间');

            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();
                $filter->like('name', '操作人')->width(6);
            });

            $grid->disableActions();
            $grid->showRefreshButton();
            $grid->paginate(5);
        });
    }

    private static function formatRemark($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $json = json_decode($value, true);
        if (is_array($json)) {
            return '<pre class="dump" style="max-width: 500px;overflow: auto">' . e(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
        }

        return e($value);
    }
}

<?php

namespace App\MerchantAdmin\Controllers;

use Dcat\Admin\Grid;
use App\Models\MerchantUser;
use Dcat\Admin\Layout\Content;
use App\Admin\Controllers\CommonController;
use Spatie\Activitylog\Models\Activity;

class LogController extends CommonController
{
    protected $disableEdit = true;

    protected $disableCreate = true;

    public function index(Content $content): Content
    {
        return $content->title(admin_trans_label('LoginLogs'))->description(trans('admin.list'))->body($this->grid());
    }

    protected function grid(): Grid
    {
        $mid = bob_merchant_user_pid();
        $merchantUserIds = MerchantUser::query()
            ->whereKey($mid)
            ->orWhere('pid', $mid)
            ->pluck('id')
            ->all();

        return Grid::make(Activity::with('causer'), function (Grid $grid) use ($merchantUserIds) {
            $grid->model()
                ->where('log_name', 'merchant')
                ->where('log_type', 'login')
                ->whereIn('causer_id', $merchantUserIds)
                ->latest('id');
            $grid->column('id', 'ID')->sortable();
            $grid->column('causer_info', __('admin.username'))->display(function () {
                $name = (string) data_get($this->causer, 'name', '');
                $username = (string) data_get($this->causer, 'username', '');

                if ($name !== '' && $username !== '') {
                    return $name . '(' . $username . ')';
                }

                return $name !== '' ? $name : ($username !== '' ? $username : ($this->causer_id ? 'ID ' . $this->causer_id : '-'));
            });
            $grid->column('account_type', '账号类型')->display(function () {
                return (int) data_get($this->causer, 'pid', 0) > 0 ? '子账号' : '主账号';
            });
            $grid->column('created_at', admin_trans_field('created_at'))->display(function () {
                return $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : '';
            });
            $grid->column('ip', admin_trans_field('ip'))->filterByValue();
            $grid->disableCreateButton();
            $grid->disableQuickEditButton();
            $grid->disableEditButton();
            $grid->disableViewButton();
            $grid->showColumnSelector();
            $grid->setActionClass(Grid\Displayers\Actions::class);
            $grid->disableActions();
            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();
                $filter->equal('ip')->width(3);
                $filter->between('created_at')->datetime()->width(3);
            });
        });
    }
}

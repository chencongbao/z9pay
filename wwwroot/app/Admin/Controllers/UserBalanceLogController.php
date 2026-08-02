<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use App\Models\DepositOrder;
use App\Models\TransferOrder;
use App\Models\UserBalanceLog;
use Illuminate\Support\Facades\App;
use App\Admin\Metrics\Admin\UserBalanceLog\Card1;
use App\Services\Cache\User\GetUserDetailService;
use App\Admin\Actions\Grid\UserBalanceLog\LogCorre;

class UserBalanceLogController extends CommonController
{
    protected $disableEdit = true;

    protected $disableCreate = true;

    protected $title = "金主交易明细";

    protected function grid(): Grid
    {
        $createdAt = request('created_at');
        $beginDate = $createdAt['start'] ?? date('Y-m-d') . " 00:00:00";
        $endDate = $createdAt['end'] ?? date('Y-m-d') . " 23:59:59";
        $adminUser = Admin::user();
        $canCorreBalanceLog = $adminUser->can('user-balance-log-corre');
        $userDetailService = App::make(GetUserDetailService::class);

        if (request('created_at') === null) {
            request()->merge(['created_at' => ['start' => $beginDate, 'end' => $endDate]]);
        }

        $query = UserBalanceLog::with([
            'user' => function ($query) {
                $query->select(['id', 'name']);
            },
            'admin' => function ($query) {
                $query->select(['id', 'name']);
            },
        ])->select($this->listColumns())->where('is_agent', 0);

        return Grid::make($query, function (Grid $grid) use ($beginDate, $endDate, $adminUser, $userDetailService, $canCorreBalanceLog) {
            $grid->column('id', '编号')->sortable();
            $grid->column('ordernumber', '交易单号');
            $grid->column('type', '交易类型')->display(function () {
                return optional(config('default.user_balance_type'))[$this->type];
            })->dot(bob_colors());
            $grid->column('amount', '交易金额');
            $grid->column('balance_amount', "账户余额");
            if ($adminUser->isAdministrator()) {
                $grid->column('type_balance_amount', "类型账户余额")->amount()->center();
            }
            $grid->column('user.name', "所属金主")->display(function ($value) {
                return bob_link($value, Admin::app()->getRoute('tusers.index', ['user_id' => $this->user_id]));
            });
            $grid->column('created_at', "交易时间");
            $grid->column('admin.name', '操作人');
            $grid->column('remark', "备注");
            $grid->column('is_corre', '冲正状态')->display(function ($value) {
                return intval($value) === 1 ? '已冲正' : '';
            })->label([1 => 'danger'])->center();
            $grid->column('corre_log_id', '冲正流水ID')->display(function ($value) {
                if (intval($value) <= 0) {
                    return '';
                }

                return bob_link((string) intval($value), Admin::app()->getRoute('user-balance-logs.index', ['id' => intval($value)]));
            })->center();
            $grid->filter(function (Grid\Filter $filter) use ($beginDate, $endDate, $userDetailService) {
                $filter->expand();
                $filter->panel();
                $filter->like('ordernumber', '交易单号')->width(3);
                $filter->equal('type', '交易类型')->select(config('default.user_balance_type'))->width(3);
                $filter->between('created_at', "交易时间")->datetime()->width(3)->default(['start' => $beginDate, 'end' => $endDate]);
                $filter->equal('user_id', "金主")->select(function ($userId) use ($userDetailService) {
                    if ($userId) {
                        $result = $userDetailService->excute($userId);

                        return empty($result) ? [] : [$result['id'] => $result['bname']];
                    }

                    return [];
                })->ajax("ajax/getUserList", "id", "bname")->width(3);
            });
            $grid->actions(function (Grid\Displayers\Actions $actions) use ($canCorreBalanceLog) {
                $actions->disableView();
                $actions->disableEdit();
                $actions->disableDelete();

                if ($canCorreBalanceLog && in_array((int) $actions->row['type'], [2, 3, 5, 6, 8, 9], true) && (int) $actions->row['is_corre'] === 0) {
                    $actions->append(new LogCorre());
                }
            });
            $grid->disableCreateButton();
            $grid->header(function () use ($beginDate, $endDate) {
                $row = new Row();
                $row->column(4, '');
                $row->column(4, new Card1(request()->all(), $beginDate, $endDate, 0));
                return '<div style="flex:1;background: grey;">' . $row->render() . '</div>';
            });
        });
    }

    private function listColumns(): array
    {
        return [
            'id', 'user_id', 'action_user_id', 'ordernumber', 'order_type', 'type', 'type_id', 'amount', 'balance_amount', 'type_balance_amount',
            'created_at', 'remark', 'is_corre', 'corre_log_id', 'is_agent',
        ];
    }
}

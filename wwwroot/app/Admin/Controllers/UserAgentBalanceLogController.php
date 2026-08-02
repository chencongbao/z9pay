<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\UserBalanceLog;
use Illuminate\Support\Facades\App;
use App\Admin\Actions\Grid\User\AddBalance;
use Illuminate\Contracts\Support\Renderable;
use App\Admin\Extensions\Layout\LeftTreeSide;
use App\Admin\Actions\Grid\User\ReduceBalance;
use App\Services\Cache\User\GetUserAgentListService;
use App\Admin\Metrics\Admin\UserAgentBalanceLog\Card1;
use App\Admin\Actions\Grid\UserAgentBalanceLog\LogCorre;
use App\Admin\Actions\Grid\UserAgentBalanceLog\ExportData;

class UserAgentBalanceLogController extends CommonController
{
    protected $disableCreate = true;

    protected $disableEdit = false;

    protected $translation = "user-agent-balance-logs";

    protected function grid(): Grid
    {
        $createdAt = request('created_at');
        $beginDate = $createdAt['start'] ?? date('Y-m-d') . " 00:00:00";
        $endDate = $createdAt['end'] ?? date('Y-m-d') . " 23:59:59";
        $agentId = (int) request('user_id', 0);
        $adminUser = Admin::user();
        $canAddBalance = $adminUser->can('user-agent-balance-log-add');
        $canReduceBalance = $adminUser->can('user-agent-balance-log-reduce');
        $canCorreBalanceLog = $adminUser->can('user-agent-balance-log-corre');
        $agentList = collect(App::make(GetUserAgentListService::class)->excute());
        $agentOptions = bob_build_select_options($agentList->toArray());

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
        ])->select($this->listColumns())->where('is_agent', 1);

        return Grid::make($query, function (Grid $grid) use ($beginDate, $endDate, $agentId, $agentList, $agentOptions, $canAddBalance, $canReduceBalance, $canCorreBalanceLog) {
            if ($agentId > 0) {
                $agent = $agentList->firstWhere('id', $agentId);
                $grid->tools()->prepend('<button class="btn btn-info"><i class="fa fa-fw fa-users" /> ' . e($agent['name'] ?? '') . '</button>');
            } else {
                $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> 全部代理</button>');
            }

            $grid->column('id', '编号')->center();
            $grid->column('ordernumber', '交易单号')->center();
            $grid->column('type', '交易类型')->display(function () {
                return optional(config('default.agent_balance_type'))->offsetGet($this->type);
            })->dot(bob_colors())->center();
            $grid->column('amount', '交易金额')->amount()->center();
            $grid->column('balance_amount', '账户余额')->amount()->center();
            $grid->column('user.name', '所属代理')->center();
            $grid->column('created_at', '交易时间')->center();
            $grid->column('admin.name', '操作人')->center();
            $grid->column('remark', "备注");
            $grid->column('is_corre', '冲正状态')->display(function ($value) {
                return intval($value) === 1 ? '已冲正' : '';
            })->label([1 => 'danger'])->center();
            $grid->column('corre_log_id', '冲正流水ID')->display(function ($value) {
                if (intval($value) <= 0) {
                    return '';
                }

                return bob_link((string) intval($value), Admin::app()->getRoute('user-agent-balance-logs.index', ['id' => intval($value)]));
            })->center();
            $grid->actions(function (Grid\Displayers\Actions $actions) use ($canCorreBalanceLog) {
                $actions->disableView();
                $actions->disableEdit();
                $actions->disableDelete();

                if ($canCorreBalanceLog && in_array((int) $actions->row['type'], [3, 4], true) && (int) $actions->row['is_corre'] === 0) {
                    $actions->append(new LogCorre());
                }
            });
            $grid->disableCreateButton();
            if ($agentId > 0) {
                if ($canAddBalance) {
                    $grid->tools()->append(new AddBalance($agentId, 'user-agent-balance-log-add'));
                }
                if ($canReduceBalance) {
                    $grid->tools()->append(new ReduceBalance($agentId, 'user-agent-balance-log-reduce'));
                }
            }
            $grid->filter(function (Grid\Filter $filter) use ($beginDate, $endDate, $agentOptions) {
                $filter->expand(true);
                $filter->panel();
                $filter->like('ordernumber', '交易单号')->width(3);
                $filter->equal('type', '交易类型')->select(config('default.agent_balance_type'))->width(3);
                $filter->equal("user_id", "代理")->select($agentOptions)->width(3);
                $filter->between('created_at', "交易时间")->datetime()->width(3)->default(['start' => $beginDate, 'end' => $endDate]);
            });

            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new ExportData());
            });

            $grid->header(function () use ($beginDate, $endDate) {
                $row = new Row();
                $row->column(4, '');
                $row->column(4, new Card1(request()->all(), $beginDate, $endDate));
                return '<div style="flex:1;background: grey;">' . $row->render() . '</div>';
            });

            $grid->wrap(function (Renderable $view) use ($agentList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($agentList) {
                    $agentUserResult = $agentList->map(function ($item) {
                        $value['parentid'] = $item['pid'];
                        $value['text'] = "【" . $item['id'] . "】" . $item['name'];
                        $value['level'] = $item['level'];
                        $value['id'] = $item['id'];
                        return $value;
                    });
                    $left = new LeftTreeSide();
                    $left->title("代理列表")->field("user_id")->default()->prependAll('全部代理')->data($agentUserResult);
                    $column->row($left);
                });
                $row->column(10, function (Column $column) use ($view) {
                    $card = Card::make($view);
                    $card->padding('15px');
                    $column->row($card);
                });
                return $row->render();
            });
        });
    }

    private function listColumns(): array
    {
        return [
            'id', 'user_id', 'action_user_id', 'ordernumber', 'type', 'type_id', 'amount', 'balance_amount', 'created_at', 'remark', 'is_corre', 'corre_log_id', 'is_agent',
        ];
    }
}

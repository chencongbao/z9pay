<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\AgentBalanceLog;
use Illuminate\Support\Facades\App;
use Illuminate\Contracts\Support\Renderable;
use App\Admin\Extensions\Layout\LeftTreeSide;
use App\Admin\Metrics\Admin\AgentBalanceLog\Card1;
use App\Admin\Actions\Grid\AgentBalanceLog\LogCorre;
use App\Admin\Actions\Grid\AgentBalanceLog\AddBalance;
use App\Admin\Actions\Grid\AgentBalanceLog\ExportData;
use App\Admin\Actions\Grid\AgentBalanceLog\ReduceBalance;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentDetailService;

class AgentBalanceLogController extends CommonController
{
    protected $disableEdit = true;
    protected $disableCreate = true;

    protected $translation = 'agent-balance-log';

    protected function grid(): Grid
    {
        $createdAt = request('created_at');
        $beginDate = $createdAt['start'] ?? date('Y-m-d') . ' 00:00:00';
        $endDate = $createdAt['end'] ?? date('Y-m-d') . ' 23:59:59';
        if ($createdAt === null) {
            request()->merge(['created_at' => ['start' => $beginDate, 'end' => $endDate]]);
        }

        $agentId = (int) request('agent_id', 0);
        $adminUser = Admin::user();
        $canAddBalance = $adminUser->can('merchant-agent-balance-log-add');
        $canReduceBalance = $adminUser->can('merchant-agent-balance-log-reduce');
        $canCorreBalanceLog = $adminUser->can('merchant-agent-balance-log-corre');
        $agentList = collect(App::make(GetMerchantAgentListService::class)->excute())->toArray();
        $agentOptions = bob_build_select_options($agentList);
        $agentTree = collect($agentList)->map(function ($item) {
            return [
                'id' => $item['id'],
                'level' => $item['level'],
                'parentid' => $item['pid'],
                'text' => '【' . $item['id'] . '】' . $item['name'],
            ];
        });
        $agentBalanceTypes = config('default.agent_balance_type', []);

        $query = AgentBalanceLog::query()->select([
            'id',
            'mid',
            'type',
            'amount',
            'remark',
            'agent_id',
            'type_id',
            'is_corre',
            'created_at',
            'ordernumber',
            'corre_log_id',
            'action_agent_id',
            'balance_amount',
        ])->with([
            'agent_user' => function ($query) {
                $query->select('id', 'name');
            },
            'merchant_info' => function ($query) {
                $query->select('merchant_user_id', 'name', 'coder', 'currency_id');
            },
            'admin',
        ]);

        return Grid::make($query, function (Grid $grid) use ($agentId, $beginDate, $endDate, $agentOptions, $agentTree, $agentBalanceTypes, $canAddBalance, $canReduceBalance, $canCorreBalanceLog) {
            if ($agentId > 0) {
                $grid->model()->where('agent_id', $agentId);
                $agent = App::make(GetMerchantAgentDetailService::class)->excute($agentId);
                $grid->tools()->prepend('<button class="btn btn-info"><i class="fa fa-fw fa-users" /> ' . e(optional($agent)->offsetGet('bname')) . '</button>');
            }

            $grid->column('id', '编号')->center();
            $grid->column('ordernumber', '交易单号')->center();
            $grid->column('type', '交易类型')->display(function () use ($agentBalanceTypes) {
                return $agentBalanceTypes[$this->type] ?? '';
            })->dot(bob_colors())->center();
            $grid->column('amount', '交易金额')->amount()->center();
            $grid->column('balance_amount', '账户余额')->amount()->center();
            $grid->column('agent_user.name', '代理')->center();
            $grid->column('merchant_info.bname', '商户')->center();
            $grid->column('created_at', '交易时间')->center();
            $grid->column('admin.name', '操作人')->center();
            $grid->column('remark');
            $grid->column('is_corre', '冲正状态')->display(function ($value) {
                return intval($value) === 1 ? '已冲正' : '';
            })->label([
                1 => 'danger',
            ])->center();
            $grid->column('corre_log_id', '冲正流水ID')->display(function ($value) {
                if (intval($value) <= 0) {
                    return '';
                }

                return bob_link((string) intval($value), Admin::app()->getRoute('agent-balance-logs.index', ['id' => intval($value)]));
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
                    $grid->tools()->append(new AddBalance($agentId));
                }
                if ($canReduceBalance) {
                    $grid->tools()->append(new ReduceBalance($agentId));
                }
            }

            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new ExportData());
            });

            $grid->filter(function (Grid\Filter $filter) use ($beginDate, $endDate, $agentOptions, $agentBalanceTypes) {
                $filter->expand();
                $filter->panel();
                $filter->like('ordernumber', '交易单号')->width(3);
                $filter->equal('type', '交易类型')->select($agentBalanceTypes)->width(3);
                $filter->between('created_at', '交易时间')->datetime()->width(3)->default(['start' => $beginDate, 'end' => $endDate]);
                $filter->equal('agent_id', '所属代理')->select($agentOptions)->width(3);
            });
            $grid->header(function () {
                $row = new Row();
                $row->column(4, '');
                $row->column(4, new Card1(request()->all()));
                return '<div style="flex:1;background: grey;">' . $row->render() . '</div>';
            });
            $grid->wrap(function (Renderable $view) use ($agentTree) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($agentTree) {
                    $left = new LeftTreeSide();
                    $left->title('代理列表')->field('agent_id')->default()->prependAll('全部代理')->data($agentTree);
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
}

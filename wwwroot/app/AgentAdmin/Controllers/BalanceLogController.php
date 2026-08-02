<?php

namespace App\AgentAdmin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\AgentBalanceLog;
use App\Models\AgentUserRelation;
use Illuminate\Support\Facades\App;
use App\Admin\Controllers\CommonController;
use App\AgentAdmin\Actions\BalanceLog\ExportData;
use App\Services\Cache\Merchant\GetMerchantListInfoService;

class BalanceLogController extends CommonController
{
    public $disableCreate = true;

    public $disableEdit = true;

    public function title()
    {
        return __('menu.titles.merchant_balance_logs');
    }

    protected function grid()
    {
        $controller = $this;
        [$beginDate, $endDate] = $this->dateRange();

        return Grid::make(AgentBalanceLog::with(['merchant_info' => function ($query) {
            $query->select('merchant_user_id', 'name', 'coder', 'currency_id');
        }]), function (Grid $grid) use ($controller, $beginDate, $endDate) {
            $admin = Admin::user();
            $grid->model()->where('agent_id', $admin->id)->orderBy('id', 'desc');
            $grid->column('id')->sortable();
            $grid->column('ordernumber', admin_trans_field('ordernumber'))->display(function ($ordernumber) use ($controller) {
                if (empty($ordernumber)) {
                    return null;
                }

                if ((int) $this->type === 1) {
                    return bob_link($ordernumber, Admin::app()->getRoute('deposit-orders.index', $controller->orderLinkParameters($ordernumber, $this->created_at)));
                }

                if ((int) $this->type === 2) {
                    return bob_link($ordernumber, Admin::app()->getRoute('transfer-orders.index', $controller->orderLinkParameters($ordernumber, $this->created_at)));
                }

                return $ordernumber;
            });
            $grid->column('merchant_info.bname', admin_trans_field('merchant_along'));
            $grid->column('type', admin_trans_field('type'))->display(function () {
                return bob_show_label(admin_trans_option($this->type, 'agent_balance_type'), $this->type, 2);
            });
            $grid->column('amount', admin_trans_field('amount'));
            $grid->column('balance_amount', admin_trans_field('balance_amount'));
            $grid->column('created_at', admin_trans_field('created_at'));
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->disableRowSelector();

            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new ExportData());
            });

            $grid->filter(function (Grid\Filter $filter) use ($admin, $beginDate, $endDate) {
                if (request('created_at') === null) {
                    request()->merge(['created_at' => ['start' => $beginDate, 'end' => $endDate]]);
                }
                $filter->expand();
                $filter->panel();

                // 只展示当前代理名下商户，避免筛选项暴露无关商户。
                $agentIds = AgentUserRelation::query()->where('parent_id', $admin->id)->pluck('child_id')->all();
                $merchantOptions = collect(App::make(GetMerchantListInfoService::class)->excute())
                    ->filter(function ($item) use ($agentIds) {
                        return in_array($item['agent_user_id'], $agentIds);
                    })
                    ->pluck('bname', 'merchant_user_id');

                $filter->equal('mid', admin_trans_label('merchant'))->select($merchantOptions)->width(3);
                $filter->equal('type', admin_trans_field('type'))->select(config('default.agent_balance_type'))->width(3);
                $filter->between('created_at', admin_trans_label('created_at'))->datetime()->width(3)->default(['start' => $beginDate, 'end' => $endDate]);
            });
        });
    }

    protected function dateRange(): array
    {
        $createdAt = request('created_at');

        return [
            $createdAt['start'] ?? date('Y-m-d') . ' 00:00:00',
            $createdAt['end'] ?? date('Y-m-d') . ' 23:59:59',
        ];
    }

    protected function orderLinkParameters(string $ordernumber, $createdAt): array
    {
        $timestamp = strtotime((string) $createdAt);
        $date = $timestamp === false ? date('Y-m-d') : date('Y-m-d', $timestamp);

        return [
            'ordernumber' => $ordernumber,
            'created_at' => [
                'start' => $date . ' 00:00:00',
                'end' => $date . ' 23:59:59',
            ],
        ];
    }
}

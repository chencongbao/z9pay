<?php

namespace App\AgentAdmin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use App\Models\DepositOrder;
use App\Models\AgentUserRelation;
use Illuminate\Support\Facades\App;
use App\Admin\Controllers\CommonController;
use App\AgentAdmin\Actions\DepositOrder\ExportData;
use App\Admin\Metrics\AgentAdmin\DepositOrder\Card1;
use App\Admin\Metrics\AgentAdmin\DepositOrder\Card2;
use App\Admin\Metrics\AgentAdmin\DepositOrder\Card3;
use App\Admin\Metrics\AgentAdmin\DepositOrder\Card4;
use App\Services\Cache\Merchant\GetMerchantListInfoService;

class DepositOrderController extends CommonController
{
    protected $translation = 'merchant-deposit-order';

    public $disableCreate = true;
    public $disableEdit = true;

    protected function grid(): Grid
    {
        return Grid::make(DepositOrder::with(['merchant_info' => function ($query) {
            $query->select('merchant_user_id', 'name');
        }]), function (Grid $grid) {
            $agentId = Admin::user()->id;
            $childAgentIds = AgentUserRelation::where('parent_id', $agentId)->pluck('child_id')->toArray();
            $currencyNames = collect(config('default.currency'))->pluck('name', 'id')->toArray();
            $paymentNames = collect(config('payment'))->pluck('name', 'id')->toArray();

            [$beginDate, $endDate] = $this->orderDateRange();

            // 只查询列表展示需要的字段，避免代理端订单列表加载过多无用列。
            $grid->model()->whereIn('merchant_agent1_id', $childAgentIds)->select([
                'id', 'mid', 'order_no', 'ordernumber', 'status',
                'pay_amount', 'actual_amount', 'currency_id', 'payment_id',
                'success_time', 'created_at',
                'merchant_agent1_id', 'merchant_agent1_commission', 'merchant_agent1_rate',
                'merchant_agent2_id', 'merchant_agent2_commission', 'merchant_agent2_rate',
                'merchant_agent3_id', 'merchant_agent3_commission', 'merchant_agent3_rate',
            ])->orderBy('id', 'desc');

            $grid->column('id')->sortable();
            $grid->column('merchant_info_name', __('merchant-user.labels.merchant'))->display(function () {
                return '【#' . optional($this->merchant_info)->offsetGet('merchant_user_id') . '】' . optional($this->merchant_info)->offsetGet('name');
            });
            $grid->column('order_no', admin_trans_label('order_no'));
            $grid->column('ordernumber', admin_trans_label('ordernumber'));
            $grid->column('status', admin_trans_label('status'))->display(function () {
                return bob_show_label(admin_trans_option($this->status, 'deposit_status'), $this->status, 2);
            });
            $grid->column('pay_amount', admin_trans_field('pay_amount'))->display(function ($value) {
                return bob_unit_format($value);
            });
            $grid->column('actual_amount', admin_trans_field('actual_amount'))->display(function ($value) {
                return bob_unit_format($value);
            });
            $grid->column('fee', admin_trans_label('commision_fee'))->display(function () use ($agentId) {
                if ($this->merchant_agent1_id == $agentId) {
                    return bob_unit_format($this->merchant_agent1_commission);
                }
                if ($this->merchant_agent2_id == $agentId) {
                    return bob_unit_format($this->merchant_agent2_commission);
                }
                if ($this->merchant_agent3_id == $agentId) {
                    return bob_unit_format($this->merchant_agent3_commission);
                }

                return null;
            });
            $grid->column('rate', admin_trans_label('commision_rate'))->display(function () use ($agentId) {
                if ($this->merchant_agent1_id == $agentId) {
                    return (floatval($this->merchant_agent1_rate) * 100) . '%';
                }
                if ($this->merchant_agent2_id == $agentId) {
                    return (floatval($this->merchant_agent2_rate) * 100) . '%';
                }
                if ($this->merchant_agent3_id == $agentId) {
                    return (floatval($this->merchant_agent3_rate) * 100) . '%';
                }

                return null;
            });
            $grid->column('currency_id', admin_trans_field('currency'))->display(function () use ($currencyNames) {
                return $currencyNames[$this->currency_id] ?? null;
            });
            $grid->column('payment_name', admin_trans_label('payment_type'))->display(function () use ($paymentNames) {
                return $paymentNames[$this->payment_id] ?? null;
            });
            $grid->column('success_time', admin_trans_label('success_time'))->display(function ($value) {
                if ($this->status == 5 && $value > 0) {
                    return date('Y-m-d H:i:s', $value);
                }

                return null;
            });
            $grid->column('created_at', admin_trans_label('created_at'));
            $grid->disableRowSelector();
            $grid->disableCreateButton();
            $grid->disableDeleteButton();
            $grid->disableEditButton();
            $grid->disableActions();

            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new ExportData());
            });

            $grid->header(function () use ($beginDate, $endDate) {
                $row = new Row();
                $params = request()->all();

                $row->column(3, new Card1($params, $beginDate, $endDate));
                $row->column(3, new Card2($params, $beginDate, $endDate));
                $row->column(3, new Card3($params, $beginDate, $endDate));
                $row->column(3, new Card4($params, $beginDate, $endDate));

                return $row;
            });

            $grid->filter(function (Grid\Filter $filter) use ($beginDate, $endDate, $childAgentIds, $paymentNames) {
                if (request('created_at') === null) {
                    request()->merge(['created_at' => ['start' => $beginDate, 'end' => $endDate]]);
                }
                $filter->expand();
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->equal('ordernumber', admin_trans_label('ordernumber'))->width(3);
                $filter->equal('order_no', admin_trans_label('order_no'))->width(3);
                $filter->equal('status', admin_trans_label('order_status'))->select(collect(config('default.deposite_status'))->transform(function ($item, $key) {
                    return admin_trans_option($key, 'deposit_status') ?: $item;
                })->toArray())->width(3);
                $filter->equal('payment_id', admin_trans_label('payment_type'))->select($paymentNames)->width(3);
                $filter->between('created_at', admin_trans_label('created_at'))->datetime()->width(3)->default(['start' => $beginDate, 'end' => $endDate]);
                $merchantOptions = collect(App::make(GetMerchantListInfoService::class)->excute())->filter(function ($item) use ($childAgentIds) {
                    return in_array($item['agent_user_id'], $childAgentIds);
                })->pluck('bname', 'merchant_user_id')->toArray();
                $filter->equal('mid', __('merchant-user.labels.merchant'))->select($merchantOptions)->width(3);
                $filter->whereBetween('success_time', function ($q) {
                    $start = $this->input['start'] ?? null;
                    $end = $this->input['end'] ?? null;
                    if ($start !== null) {
                        $q->where('success_time', '>=', strtotime($start));
                    }
                    if ($end !== null) {
                        $q->where('success_time', '<=', strtotime($end));
                    }
                }, admin_trans_label('success_time'))->datetime()->width(3);
            });
        });
    }

    protected function orderDateRange(): array
    {
        $createdAt = request('created_at');

        return [
            $createdAt['start'] ?? date('Y-m-d') . ' 00:00:00',
            $createdAt['end'] ?? date('Y-m-d') . ' 23:59:59',
        ];
    }
}

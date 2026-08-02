<?php

namespace App\AgentAdmin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use App\Models\TransferOrder;
use App\Models\AgentUserRelation;
use Illuminate\Support\Facades\App;
use App\Admin\Controllers\CommonController;
use App\AgentAdmin\Actions\TransferOrder\ExportData;
use App\Admin\Metrics\AgentAdmin\TransferOrder\Card1;
use App\Admin\Metrics\AgentAdmin\TransferOrder\Card2;
use App\Admin\Metrics\AgentAdmin\TransferOrder\Card3;
use App\Services\Cache\Merchant\GetMerchantListInfoService;

class TransferOrderController extends CommonController
{
    protected $translation = 'merchant-transfer-order';

    protected $disableCreate = true;
    protected $disableEdit = true;

    public function title(): string
    {
        return __('merchant-transfer-order.labels.TransferOrder');
    }

    protected function grid(): Grid
    {
        return Grid::make(TransferOrder::with(['merchant_info' => function ($query) {
            $query->select('merchant_user_id', 'name');
        }]), function (Grid $grid) {
            $agentId = Admin::user()->id;
            $childAgentIds = AgentUserRelation::where('parent_id', $agentId)->pluck('child_id')->toArray();
            $currencyNames = collect(config('default.currency'))->pluck('name', 'id')->toArray();

            [$beginDate, $endDate] = $this->orderDateRange();

            // 代理端代付订单只读取列表展示字段，减少大表返回数据量。
            $grid->model()->where('type', 0)->whereIn('merchant_agent1_id', $childAgentIds)->select([
                'id', 'mid', 'type', 'order_no', 'ordernumber', 'status',
                'amount', 'actual_amount', 'currency_id', 'success_time', 'created_at',
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
                return bob_show_label(admin_trans_option($this->status, 'transfer_status'), $this->status, 3);
            });
            $grid->column('amount', admin_trans_field('pay_amount'))->display(function ($value) {
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
            $grid->column('success_time', admin_trans_label('success_time'))->display(function ($value) {
                if ($this->status == 4 && $value > 0) {
                    return date('Y-m-d H:i:s', $value);
                }

                return null;
            });
            $grid->column('created_at', admin_trans_label('created_at'));
            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->disableDeleteButton();
            $grid->disableActions();
            $grid->disableRowSelector();

            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new ExportData());
            });

            $grid->header(function () use ($beginDate, $endDate) {
                $row = new Row();
                $params = request()->all();

                $row->column(4, new Card1($params, $beginDate, $endDate));
                $row->column(4, new Card2($params, $beginDate, $endDate));
                $row->column(4, new Card3($params, $beginDate, $endDate));

                return $row;
            });

            $grid->filter(function (Grid\Filter $filter) use ($beginDate, $endDate, $childAgentIds) {
                if (request('created_at') === null) {
                    request()->merge(['created_at' => ['start' => $beginDate, 'end' => $endDate]]);
                }
                $filter->expand();
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->equal('amount', admin_trans_field('pay_amount'))->width(3);
                $filter->equal('actual_amount', admin_trans_field('actual_amount'))->width(3);
                $filter->equal('ordernumber', admin_trans_label('ordernumber'))->width(3);
                $filter->equal('order_no', admin_trans_label('order_no'))->width(3);
                $filter->equal('status', admin_trans_label('order_status'))->select(collect(config('default.transfer_status'))->transform(function ($item, $key) {
                    return admin_trans_option($key, 'transfer_status') ?: $item;
                })->toArray())->width(3);
                $merchantOptions = collect(App::make(GetMerchantListInfoService::class)->excute())->filter(function ($item) use ($childAgentIds) {
                    return in_array($item['agent_user_id'], $childAgentIds);
                })->pluck('bname', 'merchant_user_id')->toArray();
                $filter->equal('mid', __('merchant-user.labels.merchant'))->select($merchantOptions)->width(3);
                $filter->between('created_at', admin_trans_label('created_at'))->datetime()->width(3)->default(['start' => $beginDate, 'end' => $endDate]);
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

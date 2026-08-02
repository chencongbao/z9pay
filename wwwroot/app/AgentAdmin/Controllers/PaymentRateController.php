<?php

namespace App\AgentAdmin\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\MerchantPayment;
use App\Models\AgentUserRelation;
use Illuminate\Support\Facades\App;
use App\Admin\Controllers\CommonController;
use App\Admin\Extensions\Layout\LeftSide;
use Illuminate\Contracts\Support\Renderable;
use App\Services\Cache\Merchant\GetMerchantListInfoService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentDetailService;

class PaymentRateController extends CommonController
{
    protected $disableEdit = true;
    protected $disableCreate = true;

    protected $translation = 'agent-rates-agent-payments';

    public function title(): string
    {
        return admin_trans_label('payment-rates');
    }

    protected function grid(): Grid
    {
        $agentId = Admin::user()->id;
        $childAgentIds = AgentUserRelation::where('parent_id', $agentId)->pluck('child_id')->toArray();
        $merchantList = App::make(GetMerchantListInfoService::class)->excute();
        $merchantOptions = collect($merchantList)->filter(function ($item) use ($agentId) {
            return $item['status'] == 1 && $item['agent_user_id'] == $agentId;
        })->pluck('bname', 'merchant_user_id')->toArray();
        $leftMerchantList = array_filter($merchantList, function ($item) use ($childAgentIds) {
            return in_array($item['agent_user_id'], $childAgentIds);
        });
        $paymentMap = collect(config('payment'))->keyBy('id');
        $agentDetailService = App::make(GetMerchantAgentDetailService::class);
        $agentCache = [];
        $getAgentDetail = function ($agentUserId) use (&$agentCache, $agentDetailService) {
            $agentUserId = intval($agentUserId);
            if ($agentUserId <= 0) {
                return [];
            }
            if (!array_key_exists($agentUserId, $agentCache)) {
                $agentCache[$agentUserId] = $agentDetailService->excute($agentUserId) ?: [];
            }

            return $agentCache[$agentUserId];
        };

        $query = MerchantPayment::query()->select([
            'id',
            'merchant_user_id',
            'agent_user_id',
            'payment_id',
            'agent1_rate',
            'agent2_rate',
            'agent3_rate',
        ])->with(['merchant_info' => function ($query) {
            $query->select('merchant_user_id', 'agent_user_id', 'name', 'coder');
        }]);

        return Grid::make($query, function (Grid $grid) use ($agentId, $merchantOptions, $leftMerchantList, $paymentMap, $getAgentDetail) {
            $grid->model()->where('agent_user_id', $agentId);
            $grid->column('merchant_info_base', __('menu.titles.information'))->display(function () {
                if (empty($this->merchant_info)) {
                    return null;
                }

                return '【#' . $this->merchant_info->merchant_user_id . '】' . $this->merchant_info->name;
            });
            $grid->column('payment_name', __('menu.titles.admin_operation_manager_payment'))->display(function () use ($paymentMap) {
                $payment = $paymentMap->get($this->payment_id);
                if (empty($payment)) {
                    return null;
                }

                return $payment['name'] . '【' . $payment['code'] . '】';
            });
            $grid->column('merchant_agent_info', admin_trans_field('agent_info'))->display(function () use ($agentId, $getAgentDetail) {
                if (empty($this->merchant_info) || intval($this->merchant_info->agent_user_id) <= 0) {
                    return null;
                }

                $agent = $getAgentDetail($this->merchant_info->agent_user_id);
                if (empty($agent)) {
                    return null;
                }

                $data = [];
                if ($agent['id'] == $agentId) {
                    $data[] = [admin_trans_field('one_agent'), '【#' . $agent['id'] . '】' . $agent['name']];
                }
                if (!empty($agent['one']) && $agentId == $agent['one']['id']) {
                    $data[] = [admin_trans_field('two_agent'), '【#' . $agent['one']['id'] . '】' . $agent['one']['name']];
                }
                if (!empty($agent['two']) && $agentId == $agent['two']['id']) {
                    $data[] = [admin_trans_field('three_agent'), '【#' . $agent['two']['id'] . '】' . $agent['two']['name']];
                }

                return !empty($data) ? bob_show_table_info($data, [], ['tr-1']) : null;
            })->top();

            $grid->column('rate', admin_trans_label('commision_rate'))->display(function () use ($agentId, $getAgentDetail) {
                if (empty($this->merchant_info) || intval($this->merchant_info->agent_user_id) <= 0) {
                    return null;
                }

                $agent = $getAgentDetail($this->merchant_info->agent_user_id);
                if (empty($agent)) {
                    return null;
                }

                $data = [];
                if ($agentId == $agent['id']) {
                    $data[] = [admin_trans_field('one_agent_rate'), bob_amount_format($this->agent1_rate) . '%'];
                }
                if (!empty($agent['one']) && $agentId == $agent['one']['id']) {
                    $data[] = [admin_trans_field('two_agent_rate'), bob_amount_format($this->agent2_rate) . '%'];
                }
                if (!empty($agent['two']) && $agentId == $agent['two']['id']) {
                    $data[] = [admin_trans_field('three_agent_rate'), bob_amount_format($this->agent3_rate) . '%'];
                }

                return !empty($data) ? bob_show_table_info($data, [], ['tr-2']) : null;
            })->top();
            $grid->disableCreateButton();
            $grid->disableActions();
            $grid->disableRowSelector();
            $grid->withBorder();

            $grid->filter(function (Grid\Filter $filter) use ($merchantOptions) {
                $filter->expand(true);
                $filter->panel();
                $filter->equal('merchant_user_id', admin_trans_label('merchant'))->select($merchantOptions)->width(3);
            });

            $grid->wrap(function (Renderable $view) use ($leftMerchantList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($leftMerchantList) {
                    $left = new LeftSide();
                    $left->title(admin_trans_field('merchant_list'))->field('merchant_user_id')->default()->data($leftMerchantList);
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

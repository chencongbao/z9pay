<?php

namespace App\Admin\Controllers\Agent;

use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\MerchantPayment;
use App\Models\AgentUserRelation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use App\Admin\Controllers\CommonController;
use Illuminate\Contracts\Support\Renderable;
use App\Admin\Extensions\Layout\LeftTreeSide;
use App\Services\Common\DataFormatBnameService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentDetailService;

class PaymentRateController extends CommonController
{

    public $title = '商户代理费率';

    protected $disableEdit = true;
    protected $disableCreate = true;

    protected $translation = "rates-agent-payments";

    protected function grid()
    {
        $agentId = intval(Request::input('agent_id', 0));
        $formatService = App::make(DataFormatBnameService::class);
        $merchantBaseInfoService = App::make(CacheMerchantBaseInfoService::class);
        $agentDetailService = App::make(GetMerchantAgentDetailService::class);
        $agentList = $formatService->excute(App::make(GetMerchantAgentListService::class)->excute());
        $agentOptions = bob_build_select_options(collect($agentList)->toArray());
        $paymentMap = collect(config('payment'))->keyBy('id');

        $query = MerchantPayment::query()->select(['id', 'merchant_user_id', 'agent_user_id', 'payment_id', 'agent1_rate', 'agent2_rate', 'agent3_rate'])->with(['merchant_info' => function ($query) {
            $query->select('merchant_user_id', 'agent_user_id');
        }]);

        return Grid::make($query, function (Grid $grid) use ($agentId, $formatService, $merchantBaseInfoService, $agentDetailService, $agentList, $agentOptions, $paymentMap) {
            if ($agentId > 0) {
                $agent = $formatService->excute($agentDetailService->excute($agentId));
                $grid->tools()->prepend('<span class="btn btn-primary"><i class="fa fa-fw fa-users" /> ' . optional($agent)->offsetGet('bname') . '</span>');
            }

            $grid->column('merchant_info_base', "商户信息")->display(function () use ($formatService, $merchantBaseInfoService) {
                return optional($formatService->excute($merchantBaseInfoService->excute($this->merchant_user_id)))->offsetGet('bname');
            });

            $grid->column('payment_name', '通道编码')->display(function () use ($paymentMap) {
                $payment = $paymentMap->get($this->payment_id);
                if (!empty($payment)) {
                    return $payment['name'] . "【" . $payment['code'] . "】";
                }
            });

            $grid->column('merchant_agent_info', "代理信息")->display(function () use ($agentId, $formatService, $agentDetailService) {
                if (empty($this->merchant_info) || intval($this->merchant_info->agent_user_id) <= 0) {
                    return null;
                }

                $agent = $formatService->excute($agentDetailService->excute($this->merchant_info->agent_user_id));
                if (empty($agent)) {
                    return null;
                }

                $data = [['一级代理', $agent['bname'] ?? '']];
                $colors = [$agentId === intval($agent['id'] ?? 0) ? "tr-1" : ""];
                if (!empty($agent['one'])) {
                    $data[] = ['二级代理', $agent['one']['bname'] ?? ''];
                    $colors[] = $agentId === intval($agent['one']['id'] ?? 0) ? "tr-1" : "";
                }
                if (!empty($agent['two'])) {
                    $data[] = ['三级代理', $agent['two']['bname'] ?? ''];
                    $colors[] = $agentId === intval($agent['two']['id'] ?? 0) ? "tr-1" : "";
                }

                return bob_show_table_info($data, [], $colors, 3);
            })->top();

            $grid->column('rate', "费率")->display(function () use ($agentId, $agentDetailService) {
                $colors = [];
                if (!empty($this->merchant_info) && intval($this->merchant_info->agent_user_id) > 0) {
                    $agent = $agentDetailService->excute($this->merchant_info->agent_user_id);
                    if (!empty($agent)) {
                        $colors[] = $agentId === intval($agent['id'] ?? 0) ? "tr-2" : "";
                        if (!empty($agent['one'])) {
                            $colors[] = $agentId === intval($agent['one']['id'] ?? 0) ? "tr-2" : "";
                        }
                        if (!empty($agent['two'])) {
                            $colors[] = $agentId === intval($agent['two']['id'] ?? 0) ? "tr-2" : "";
                        }
                    }
                }

                $data = [
                    ["一级代理费率", bob_amount_format($this->agent1_rate) . "%"],
                    ["二级代理费率", bob_amount_format($this->agent2_rate) . "%"],
                    ["三级代理费率", bob_amount_format($this->agent3_rate) . "%"],
                ];

                return bob_show_table_info($data, [], $colors);
            })->top();

            $grid->disableCreateButton();
            $grid->disableActions();
            $grid->disableRowSelector();
            $grid->withBorder();

            $grid->filter(function (Grid\Filter $filter) use ($agentOptions) {
                $filter->expand();
                $filter->panel();
                $filter->where('agent_id', function ($query) {
                    $query->whereIn('agent_user_id', AgentUserRelation::where('parent_id', $this->input)->pluck("child_id"));
                }, "所属代理")->select($agentOptions)->width(3);
            });

            // 左侧代理树与右侧费率列表组合展示。
            $grid->wrap(function (Renderable $view) use ($agentList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($agentList) {
                    $agentTree = collect($agentList)->transform(function ($item) {
                        return [
                            'parentid' => $item['pid'],
                            'text' => $item['bname'],
                            'level' => $item['level'],
                            'id' => $item['id'],
                        ];
                    });
                    $left = new LeftTreeSide();
                    $left->title("代理列表")->field("agent_id")->default()->prependAll('全部代理')->data($agentTree);
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

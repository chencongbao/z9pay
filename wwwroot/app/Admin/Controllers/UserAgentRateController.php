<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use App\Models\UserRelation;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use Illuminate\Support\Facades\App;
use App\Models\UserModel as Administrator;
use Illuminate\Contracts\Support\Renderable;
use App\Admin\Extensions\Layout\LeftTreeSide;
use App\Services\Cache\User\GetUserDetailService;
use App\Services\Cache\User\GetUserAgentListService;

class UserAgentRateController extends CommonController
{
    protected $disableCreate = true;

    protected $disableEdit = false;

    protected $translation = "user-agent-rates";

    protected function grid(): Grid
    {
        $requestedAgentId = (int) request('pid', 0);
        $agentList = collect(App::make(GetUserAgentListService::class)->excute());
        $activeAgent = $requestedAgentId > 0 ? $agentList->firstWhere('id', $requestedAgentId) : null;
        $agentId = $activeAgent ? $requestedAgentId : 0;
        if ($requestedAgentId > 0 && !$activeAgent) {
            request()->query->remove('pid');
            request()->request->remove('pid');
        }

        $agentOptions = bob_build_select_options($agentList->toArray());
        $userDetailService = App::make(GetUserDetailService::class);
        $controller = $this;

        $query = Administrator::query()->select($this->listColumns())->where('is_agent', 0);

        return Grid::make($query, function (Grid $grid) use ($agentId, $agentList, $agentOptions, $userDetailService, $controller) {
            if ($agentId > 0) {
                $result = $agentList->firstWhere('id', $agentId);
                $bname = is_array($result) ? ($result['bname'] ?? '') : optional($result)->offsetGet('bname');
                if ($bname !== '') {
                    $grid->tools()->prepend('<span class="btn btn-primary"><i class="fa fa-fw fa-users" /> ' . e($bname) . '</span>');
                }
            }

            $grid->column('bname', "金主名称");
            $grid->column('parent_user_info', "金主代理")->display(function () use ($agentId, $userDetailService, $controller) {
                $user = $userDetailService->excute($this->id);
                if (empty($user)) {
                    return '';
                }

                $data = $controller->agentRows($user);
                return empty($data) ? '' : bob_show_table_info($data, [], $controller->agentColors($user, $agentId, "tr-2"), 5);
            });
            $grid->column('deposit_rate', "代收费率")->display(function () use ($agentId, $userDetailService, $controller) {
                $user = $userDetailService->excute($this->id);
                return bob_show_table_info($controller->rateRows($this, 'deposit'), [], $controller->agentColors($user, $agentId, "tr-5"), 5);
            });
            $grid->column('transfer_rate', "代付费率")->display(function () use ($agentId, $userDetailService, $controller) {
                $user = $userDetailService->excute($this->id);
                return bob_show_table_info($controller->rateRows($this, 'transfer'), [], $controller->agentColors($user, $agentId, "tr-4"), 5);
            });
            $grid->column('settlement_rate', "结算费率")->display(function () use ($agentId, $userDetailService, $controller) {
                $user = $userDetailService->excute($this->id);
                return bob_show_table_info($controller->rateRows($this, 'settlement'), [], $controller->agentColors($user, $agentId, "tr-3"), 5);
            });
            $grid->disableCreateButton();
            $grid->disableActions();

            $grid->filter(function (Grid\Filter $filter) use ($agentOptions) {
                $filter->expand();
                $filter->panel();
                $filter->where("pid", function ($query) {
                    $query->whereIn('id', UserRelation::query()->select('child_id')->where('parent_id', $this->input));
                }, "代理")->select($agentOptions)->width(3);
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
                    $left->title("代理列表")->field("pid")->default()->prependAll('全部代理')->data($agentUserResult);
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
            'id', 'name', 'is_agent', 'agent1_rate', 'agent2_rate', 'agent3_rate', 'agent4_rate', 'agent5_rate',
            'deposit_agent1_rate', 'deposit_agent2_rate', 'deposit_agent3_rate', 'deposit_agent4_rate', 'deposit_agent5_rate',
            'transfer_agent1_rate', 'transfer_agent2_rate', 'transfer_agent3_rate', 'transfer_agent4_rate', 'transfer_agent5_rate',
            'settlement_agent1_rate', 'settlement_agent2_rate', 'settlement_agent3_rate', 'settlement_agent4_rate', 'settlement_agent5_rate',
        ];
    }

    private function agentRows($user): array
    {
        $rows = [];
        foreach ($this->agentLevels() as $key => $label) {
            if (!empty($user[$key])) {
                $rows[] = [$label, "【#" . $user[$key]['id'] . "】" . $user[$key]['name']];
            }
        }

        return $rows;
    }

    private function agentColors($user, int $agentId, string $color): array
    {
        if (empty($user)) {
            return [];
        }

        $colors = [];
        foreach (array_keys($this->agentLevels()) as $key) {
            if (!empty($user[$key])) {
                $colors[] = $agentId == $user[$key]['id'] ? $color : "";
            }
        }

        return $colors;
    }

    private function rateRows($row, string $prefix): array
    {
        return [
            ["一级代理费率", (floatval($row->{$prefix . '_agent1_rate'}) ?: floatval($row->agent1_rate)) . "%"],
            ["二级代理费率", (floatval($row->{$prefix . '_agent2_rate'}) ?: floatval($row->agent2_rate)) . "%"],
            ["三级代理费率", (floatval($row->{$prefix . '_agent3_rate'}) ?: floatval($row->agent3_rate)) . "%"],
            ["四级代理费率", (floatval($row->{$prefix . '_agent4_rate'}) ?: floatval($row->agent4_rate)) . "%"],
            ["五级代理费率", (floatval($row->{$prefix . '_agent5_rate'}) ?: floatval($row->agent5_rate)) . "%"],
        ];
    }

    private function agentLevels(): array
    {
        return [
            'one' => '一级代理',
            'two' => '二级代理',
            'three' => '三级代理',
            'four' => '四级代理',
            'five' => '五级代理',
        ];
    }
}

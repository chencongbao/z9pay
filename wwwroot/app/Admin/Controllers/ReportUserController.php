<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use App\Models\ReportUser;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Tab;
use Dcat\Admin\Widgets\Card;
use Dcat\Admin\Layout\Column;
use App\Models\ReportUserMerchant;
use Illuminate\Support\Facades\App;
use App\Admin\Metrics\Admin\CommonCard;
use App\Admin\Extensions\Layout\LeftSide;
use Illuminate\Contracts\Support\Renderable;
use App\Services\Cache\User\GetUserListService;
use App\Services\Cache\User\GetUserDetailService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class ReportUserController extends CommonController
{
    public $title = '金主报表';

    public $source_id = 0;

    protected $disableCreate = true;

    protected $disableEdit = true;

    protected function grid(): Grid
    {
        $merchantUserId = (int) request('mid', 0);
        if ($merchantUserId <= 0) {
            request()->query->remove('mid');
            request()->request->remove('mid');
        }
        $isAllUser = request()->has('uid') && (int) request('uid') <= 0;
        if ($isAllUser) {
            request()->query->remove('uid');
            request()->request->remove('uid');
        }
        $sourceId = (int) request('source_id', 1);
        $sourceId = in_array($sourceId, [1, 2, 3], true) ? $sourceId : 1;
        $this->source_id = $sourceId;

        $userListService = App::make(GetUserListService::class);
        $userDetailService = App::make(GetUserDetailService::class);
        $merchantBaseInfoService = App::make(CacheMerchantBaseInfoService::class);
        $userList = $userListService->excute();
        $userId = (int) request('uid', 0);

        $isMerchantReport = $merchantUserId > 0;
        $reportModelClass = $isMerchantReport ? ReportUserMerchant::class : ReportUser::class;
        $model = $reportModelClass::query()
            ->select($this->reportColumns($sourceId, $isMerchantReport))
            ->orderByDesc('date_add')
            ->orderByDesc('id');

        return Grid::make($model, function (Grid $grid) use ($userId, $sourceId, $merchantUserId, $reportModelClass, $userList, $userDetailService, $merchantBaseInfoService) {
            $user = $userDetailService->excute($userId);
            $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> '.optional($user)->offsetGet('bname').'</button>');

            $grid->column('id')->sortable();
            $grid->column('date_add', '日期');

            if ($sourceId === 1) {
                $grid->column('deposit_order_number_total', '代收单数');
                $grid->column('deposit_order_number_success', '代收成功单数');
                $grid->column('deposit_order_number_fail', '代收失败单数');
                $grid->column('deposit_order_number_overtime', '代收超时单数');
                $grid->column('deposit_order_number_swiping', '代收刷单单数');
                $grid->column('deposit_order_success_rate', '代收成功率')->display(function () {
                    return bob_percent($this->deposit_order_number_success, $this->deposit_order_number_total);
                });
                $grid->column('deposit_order_total_amount', '代收跑量')->amount();
                $grid->column('deposit_order_total_fee', '代收商户手续费')->amount();
                $grid->column('deposit_commission', '代收佣金')->amount();
                $grid->column('deposit_one_agent_commission', '代收一级代理佣金')->amount();
                $grid->column('deposit_two_agent_commission', '代收二级代理佣金')->amount();
                $grid->column('deposit_three_agent_commission', '代收三级代理佣金')->amount();
                $grid->column('deposit_four_agent_commission', '代收四级代理佣金')->amount();
                $grid->column('deposit_five_agent_commission', '代收五级代理佣金')->amount();
                $grid->column('deposit_jian_total_amount', '代收减项')->amount();
                $grid->column('deposit_add_total_amount', '代收加项')->amount();
                $grid->column('commission_jian_total_amount', '佣金减项')->amount();
                $grid->column('commission_add_total_amount', '佣金加项')->amount();
            }

            if ($sourceId === 2) {
                $grid->column('transfer_order_number_total', '代付单数');
                $grid->column('transfer_order_number_success', '代付成功单数');
                $grid->column('transfer_order_number_fail', '代付失败单数');
                $grid->column('transfer_order_success_rate', '代付成功率')->display(function () {
                    return bob_percent($this->transfer_order_number_success, $this->transfer_order_number_total);
                });
                $grid->column('transfer_order_total_amount', '代付跑量')->amount();
                $grid->column('transfer_order_total_fee', '代付商户手续费')->amount();
                $grid->column('transfer_commission', '代付佣金')->amount();
                $grid->column('transfer_one_agent_commission', '代付一级代理佣金')->amount();
                $grid->column('transfer_two_agent_commission', '代付二级代理佣金')->amount();
                $grid->column('transfer_three_agent_commission', '代付三级代理佣金')->amount();
                $grid->column('transfer_four_agent_commission', '代付四级代理佣金')->amount();
                $grid->column('transfer_five_agent_commission', '代付五级代理佣金')->amount();
                $grid->column('transfer_jian_total_amount', '代付减项')->amount();
                $grid->column('transfer_add_total_amount', '代付加项')->amount();
            }

            if ($sourceId === 3) {
                $grid->column('settlement_order_number_total', '结算单数');
                $grid->column('settlement_order_number_success', '结算成功单数');
                $grid->column('settlement_order_number_fail', '结算失败单数');
                $grid->column('settlement_order_success_rate', '结算成功率')->display(function () {
                    return bob_percent($this->settlement_order_number_success, $this->settlement_order_number_total);
                });
                $grid->column('settlement_order_total_amount', '结算跑量')->amount();
                $grid->column('settlement_order_total_fee', '结算商户手续费')->amount();
                $grid->column('settlement_commission', '结算佣金')->amount();
                $grid->column('settlement_one_agent_commission', '结算一级代理佣金')->amount();
                $grid->column('settlement_two_agent_commission', '结算二级代理佣金')->amount();
                $grid->column('settlement_three_agent_commission', '结算三级代理佣金')->amount();
                $grid->column('settlement_four_agent_commission', '结算四级代理佣金')->amount();
                $grid->column('settlement_five_agent_commission', '结算五级代理佣金')->amount();
            }

            $grid->disableActions();
            $grid->disableCreateButton();

            $grid->filter(function (Grid\Filter $filter) use ($userId, $userDetailService, $merchantBaseInfoService) {
                if ($userId > 0 && request('uid') === null) {
                    request()->merge(['uid' => $userId]);
                }

                $filter->expand(true);
                $filter->panel();
                $filter->between('date_add', '查询日期')->date()->width(4);
                $filter->equal('uid', '金主')->select(function ($uid) use ($userId, $userDetailService) {
                    $uid = $uid ?: $userId;
                    if ($uid) {
                        $result = $userDetailService->excute($uid);

                        return empty($result) ? [] : [$result['id'] => $result['bname']];
                    }

                    return [];
                })->ajax('ajax/getUserList', 'id', 'bname')->width(3)->default($userId);
                $filter->equal('mid', '商户')->select(function ($mid) use ($merchantBaseInfoService) {
                    if ($mid) {
                        $result = $merchantBaseInfoService->excute($mid);

                        return empty($result) ? [] : [$result['merchant_user_id'] => $result['bname']];
                    }

                    return [];
                })->ajax('/ajax/getMerchantList', 'merchant_user_id', 'bname')->width(3);
            });

            $grid->header(function () use ($grid, $userId, $sourceId, $merchantUserId, $reportModelClass) {
                $tab = Tab::make();
                $tab->addLink('代收', admin_route('report-users.index', $this->tabQuery(1, $userId, $merchantUserId)), $sourceId === 1);
                $tab->addLink('代付', admin_route('report-users.index', $this->tabQuery(2, $userId, $merchantUserId)), $sourceId === 2);
                $tab->addLink('结算', admin_route('report-users.index', $this->tabQuery(3, $userId, $merchantUserId)), $sourceId === 3);

                $query = $reportModelClass::query();
                $grid->model()->getQueries()->unique()->each(function ($value) use (&$query) {
                    if (in_array($value['method'], ['paginate', 'get', 'orderBy', 'orderByDesc'], true)) {
                        return;
                    }
                    $query = call_user_func_array([$query, $value['method']], $value['arguments'] ?? []);
                });

                $row = new Row();
                $row->column(4, '');
                $sumField = $this->summaryField($sourceId);
                $sumTitle = $this->summaryTitle($sourceId);
                $row->column(4, new CommonCard($sumTitle, $query->sum($sumField)));

                return '<div style="width: 100%">'.$tab.'<div style="flex:1;background: grey;">'.$row->render().'</div></div>';
            });

            $grid->wrap(function (Renderable $view) use ($userId, $userList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($userId, $userList) {
                    $left = new LeftSide();
                    $left->title('金主列表')->field('uid')->default($userId)->prependAll('全部金主', true)->data($userList);
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

    private function tabQuery(int $sourceId, int $userId, int $merchantUserId): array
    {
        $query = ['source_id' => $sourceId];
        if ($userId > 0) {
            $query['uid'] = $userId;
        }
        if ($merchantUserId > 0) {
            $query['mid'] = $merchantUserId;
        }

        return $query;
    }

    private function reportColumns(int $sourceId, bool $isMerchantReport): array
    {
        $columns = ['id', 'uid', 'date_add'];
        if ($isMerchantReport) {
            $columns[] = 'mid';
        }

        if ($sourceId === 1) {
            return array_merge($columns, [
                'deposit_order_number_total', 'deposit_order_number_success', 'deposit_order_number_fail', 'deposit_order_number_overtime', 'deposit_order_number_swiping',
                'deposit_order_total_amount', 'deposit_order_total_fee', 'deposit_commission', 'deposit_one_agent_commission', 'deposit_two_agent_commission',
                'deposit_three_agent_commission', 'deposit_four_agent_commission', 'deposit_five_agent_commission', 'deposit_jian_total_amount', 'deposit_add_total_amount',
                'commission_jian_total_amount', 'commission_add_total_amount',
            ]);
        }

        if ($sourceId === 2) {
            return array_merge($columns, [
                'transfer_order_number_total', 'transfer_order_number_success', 'transfer_order_number_fail', 'transfer_order_total_amount', 'transfer_order_total_fee',
                'transfer_commission', 'transfer_one_agent_commission', 'transfer_two_agent_commission', 'transfer_three_agent_commission', 'transfer_four_agent_commission',
                'transfer_five_agent_commission', 'transfer_jian_total_amount', 'transfer_add_total_amount',
            ]);
        }

        return array_merge($columns, [
            'settlement_order_number_total', 'settlement_order_number_success', 'settlement_order_number_fail', 'settlement_order_total_amount', 'settlement_order_total_fee',
            'settlement_commission', 'settlement_one_agent_commission', 'settlement_two_agent_commission', 'settlement_three_agent_commission',
            'settlement_four_agent_commission', 'settlement_five_agent_commission',
        ]);
    }

    private function summaryField(int $sourceId): string
    {
        return [
            1 => 'deposit_order_total_amount',
            2 => 'transfer_order_total_amount',
            3 => 'settlement_order_total_amount',
        ][$sourceId];
    }

    private function summaryTitle(int $sourceId): string
    {
        return [
            1 => '代收跑量',
            2 => '代付跑量',
            3 => '结算跑量',
        ][$sourceId];
    }
}

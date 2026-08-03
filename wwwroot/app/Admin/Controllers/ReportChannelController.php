<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Widgets\Tab;
use Dcat\Admin\Widgets\Card;
use App\Models\ReportChannel;
use Dcat\Admin\Layout\Column;
use Illuminate\Support\Facades\App;
use App\Models\ReportChannelMerchant;
use App\Admin\Extensions\Layout\LeftSide;
use App\Admin\Actions\Grid\ReportChannel\ExportData;
use Illuminate\Contracts\Support\Renderable;
use App\Services\Cache\Channel\GetChannelListService;
use App\Services\Cache\Channel\ChannelInfoByChannelIdService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class ReportChannelController extends CommonController
{
    public $title = '渠道报表';

    protected $disableCreate = true;

    protected $disableEdit = true;

    public $source_id = 1;

    protected function grid(): Grid
    {
        $mid = (int) request('mid', 0);
        if ($mid <= 0) {
            request()->query->remove('mid');
            request()->request->remove('mid');
        }
        $sourceId = (int) request('source_id', 1);
        $this->source_id = $sourceId;
        $channelList = App::make(GetChannelListService::class)->excute();
        $channelOptions = collect($channelList)->pluck('bname', 'id');
        $isAllChannel = request()->has('cid') && (int) request('cid') <= 0;
        if ($isAllChannel) {
            request()->query->remove('cid');
            request()->request->remove('cid');
        }
        $channelId = (int) request('cid', 0);

        $query = ($mid > 0 ? ReportChannelMerchant::query() : ReportChannel::query())
            ->select($this->reportColumns($sourceId, $mid > 0))
            ->orderByDesc('date_add')
            ->orderByDesc('id');
        $channelInfoService = App::make(ChannelInfoByChannelIdService::class);
        $merchantBaseInfoService = App::make(CacheMerchantBaseInfoService::class);

        return Grid::make($query, function (Grid $grid) use ($mid, $sourceId, $channelId, $channelList, $channelOptions, $channelInfoService, $merchantBaseInfoService) {
            $result = $channelInfoService->excute($channelId);
            $grid->tools()->prepend('<button class="btn btn-primary"><i class="fa fa-fw fa-users" /> ' . ($channelId > 0 ? optional($result)->offsetGet('name') : '全部渠道') . '</button>');
            $grid->column('id')->sortable()->center();
            $grid->column('date_add', '日期')->center();
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
                $grid->column('deposit_profit', '代收总利润')->amount();
            }
            if ($sourceId === 2) {
                $grid->column('transfer_order_number_total', '代付单数');
                $grid->column('transfer_order_number_success', '代付成功单数');
                $grid->column('transfer_order_number_fail', '代付失败单数');
                $grid->column('transfer_order_success_rate', '代付成功率')->display(function () {
                    return bob_percent($this->transfer_order_number_success, $this->transfer_order_number_total);
                });
                $grid->column('transfer_order_total_amount', '代付跑量')->amount();
                $grid->column('transfer_order_total_fee', '代付总手续费')->amount();
                $grid->column('transfer_profit', '代付总利润')->amount();
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
                $grid->column('settlement_profit', '结算利润')->amount();
            }

            if ($channelId <= 0) {
                $grid->column('channel_info_bname', '所属渠道')->display(function () use ($channelInfoService) {
                    $channel = $channelInfoService->excute((int) $this->cid);

                    return $channel['name'] ?? '渠道信息缺失';
                });
            }

            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new ExportData());
            });

            $grid->filter(function (Grid\Filter $filter) use ($channelId, $channelOptions, $merchantBaseInfoService) {
                $filter->expand(true);
                $filter->panel();
                $filter->between('date_add', '查询日期')->date()->width(4);
                $filter->equal('cid', '渠道')->select($channelOptions)->default($channelId)->width(3);
                $filter->equal('mid', '商户')->select(function ($mid) use ($merchantBaseInfoService) {
                    if ($mid) {
                        $result = $merchantBaseInfoService->excute($mid);
                        if (!empty($result)) {
                            return [$result['merchant_user_id'] => $result['bname']];
                        }
                    }

                    return [];
                })->ajax('/ajax/getMerchantList', 'merchant_user_id', 'bname')->width(3);
            });

            $grid->header(function () use ($mid, $sourceId, $channelId) {
                $tab = Tab::make();
                $tab->addLink('代收', admin_route('report-channels.index', $this->tabQuery(1, $channelId, $mid)), $sourceId === 1);
                $tab->addLink('代付', admin_route('report-channels.index', $this->tabQuery(2, $channelId, $mid)), $sourceId === 2);
                $tab->addLink('结算', admin_route('report-channels.index', $this->tabQuery(3, $channelId, $mid)), $sourceId === 3);

                return '<div style="width: 100%">' . $tab . '</div>';
            });

            $grid->wrap(function (Renderable $view) use ($channelId, $channelList) {
                $row = new Row();
                $row->column(2, function (Column $column) use ($channelId, $channelList) {
                    $left = new LeftSide();
                    $left->title('渠道列表')->field('cid')->default($channelId)->prependAll('全部渠道', true)->data($channelList);
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

    private function tabQuery(int $sourceId, int $channelId, int $mid): array
    {
        $query = ['source_id' => $sourceId, 'cid' => $channelId];
        if ($mid > 0) {
            $query['mid'] = $mid;
        }

        return $query;
    }

    private function reportColumns(int $sourceId, bool $withMerchant): array
    {
        $columns = ['id', 'cid', 'date_add'];
        if ($withMerchant) {
            $columns[] = 'mid';
        }

        if ($sourceId === 1) {
            return array_merge($columns, [
                'deposit_order_number_total',
                'deposit_order_number_success',
                'deposit_order_number_fail',
                'deposit_order_number_overtime',
                'deposit_order_number_swiping',
                'deposit_order_total_amount',
                'deposit_order_total_fee',
                'deposit_profit',
            ]);
        }

        if ($sourceId === 2) {
            return array_merge($columns, [
                'transfer_order_number_total',
                'transfer_order_number_success',
                'transfer_order_number_fail',
                'transfer_order_total_amount',
                'transfer_order_total_fee',
                'transfer_profit',
            ]);
        }

        return array_merge($columns, [
            'settlement_order_number_total',
            'settlement_order_number_success',
            'settlement_order_number_fail',
            'settlement_order_total_amount',
            'settlement_order_total_fee',
            'settlement_profit',
        ]);
    }
}

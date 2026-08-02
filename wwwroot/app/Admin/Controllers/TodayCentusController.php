<?php

namespace App\Admin\Controllers;

use Carbon\Carbon;
use Dcat\Admin\Admin;
use App\Models\Channel;
use App\Models\UserModel;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Grid\Filter;
use Dcat\Admin\Widgets\Box;
use Dcat\Admin\Widgets\Tab;
use App\Models\DepositOrder;
use App\Models\MerchantInfo;
use Dcat\Admin\Layout\Column;
use App\Traits\ResponseTraits;
use Dcat\Admin\Layout\Content;
use App\Extendtions\Dcat\src\Grid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Extendtions\Dcat\Widgets\BobTable;
use App\Services\Cache\User\GetUserListService;
use App\Services\Common\DataFormatBnameService;
use App\Services\Cache\Channel\GetChannelListService;
use App\Services\Cache\UserBank\GetUserBankDetailService;
use App\Services\Cache\Merchant\GetMerchantListInfoService;

class TodayCentusController extends CommonController
{
    use ResponseTraits;

    protected $disableCreate = true;

    protected $disableEdit = true;

    public function index(Content $content)
    {
        if (request('action') === 'moreinfo') {
            $search = request()->all();
            $data = [];
            $result = MerchantInfo::query()->orderBy("merchant_user_id", 'desc')->paginate(10, ['merchant_user_id', 'name', 'coder', 'currency_id']);
            if (!empty($result)) {
                foreach ($result->items() as $item) {
                    $total_order_number_2 = 0;
                    $success_order_number_2 = 0;
                    $fail_order_number_2 = 0;
                    $overtime_order_number_2 = 0;
                    $refresh_order_number_2 = 0;
                    $total_order_amount_2 = 0;
                    $search['mid'] = $item->merchant_user_id;
                    $result2 = $this->groupCount($search);
                    if (!empty($result2)) {
                        foreach ($result2 as $k => $v) {
                            $total_order_number_2 += $v['num'];
                            if ($v['status'] == 5) {
                                $total_order_amount_2 = $v['total_amount'];
                                $success_order_number_2 = $v['num'];
                            }
                            if ($v['status'] == 6) {
                                $fail_order_number_2 = $v['num'];
                            }
                            if ($v['status'] == 4) {
                                $overtime_order_number_2 = $v['num'];
                            }
                            if ($v['status'] == 2) {
                                $refresh_order_number_2 = $v['num'];
                            }
                        }
                    }
                    $data[] = [
                        'name' => $item->bname,
                        'total_order_number' => $total_order_number_2,
                        'success_order_number' => $this->formatData($success_order_number_2, $total_order_number_2, "#597927"),
                        'fail_order_number' => $this->formatData($fail_order_number_2, $total_order_number_2, "#951D1D"),
                        'overtime_order_number' => $this->formatData($overtime_order_number_2, $total_order_number_2, "#9B7D31"),
                        'refresh_order_number' => $this->formatData($refresh_order_number_2, $total_order_number_2, "#000B7B"),
                        'total_order_amount' => '<span style="color: red">' . $total_order_amount_2 . '</span>',
                    ];
                }
            }
            return $this->success('', ['data' => $data]);
        }
        $url = request()->url();
        $link = Admin::app()->getRoute("today.index");
        Admin::script(
            <<<JS
const BaseUrl = "{$url}";
function hasUrlParameters(url) {
    return /\#/.test(url);
}
        Dcat.ready(function () {
            $(document).off('click', '.showListTable').on('click', '.showListTable', function () {
                Dcat.loading({background:"rgba(0,0,0,1)"});
                let that = this;
                  $.ajax({
                    type: 'GET',
                    data:{action:"moreinfo",page:$(this).data('page'),begin_date:$(this).data('begin_date'),end_date:$(this).data('end_date'),user_id:$(this).data('user_id'),channel_id:$(this).data('channel_id'),payment_id:$(this).data('payment_id')},
                    url:"{$link}",
                    success:function(res){
                         Dcat.loading(false);
                        if(res.code == -9999){
                            Dcat.error(res.message);
                            return;
                        }
                        $(that).data("page",parseInt($(that).data('page')) + 1);
                        if(res.data.data.length > 0){
                            res.data.data.map((value,index)=>{
                                let html = '<tr class="tr">'
                                    +'<td style="padding-top: 10px;padding-bottom: 10px;vertical-align: center;height: auto; ">'+value.name+'</td>'
                                    +'<td style="padding-top: 10px;padding-bottom: 10px;vertical-align: center;height: auto; ">'+value.total_order_number+'</td>'
                                    +'<td style="padding-top: 10px;padding-bottom: 10px;vertical-align: center;height: auto; ">'+value.success_order_number+'</td>'
                                    +'<td style="padding-top: 10px;padding-bottom: 10px;vertical-align: center;height: auto; ">'+value.fail_order_number+'</td>'
                                    +'<td style="padding-top: 10px;padding-bottom: 10px;vertical-align: center;height: auto; ">'+value.overtime_order_number+'</td>'
                                    +'<td style="padding-top: 10px;padding-bottom: 10px;vertical-align: center;height: auto; ">'+value.refresh_order_number+'</td>'
                                    +'<td style="padding-top: 10px;padding-bottom: 10px;vertical-align: center;height: auto; ">'+value.total_order_amount+'</td>'
                                +'</tr>';
                                $(that).parent().find('table tbody').append(html);
                            });
                        }else{
                            $(that).addClass("hidden");
                        }
                    }
                });
            });
        });
JS
        );
        return $content->title("商户监控")->row(function (Row $row) {
            $tab = Tab::make();

            $channel_result = App::make(GetChannelListService::class)->excute();
            $merchant_result = App::make(GetMerchantListInfoService::class)->excute();
            $payment_result = App::make(DataFormatBnameService::class)->excute(array_filter(config('payment'), function ($v) {
                return $v['id'] > 0;
            }));
            $user_result = App::make(GetUserListService::class)->excute();

            $tab->add('商户', view("admin.home.merchant-order.tab-merchant", ['lists' => $merchant_result]), true, 'merchant');
            $tab->add('渠道', view("admin.home.merchant-order.tab-channel", ['lists' => $channel_result]), false, 'channel');
            $tab->add('通道', view("admin.home.merchant-order.tab-payment", ['lists' => $payment_result]), false, 'payment');
            $tab->add('金主', view("admin.home.merchant-order.tab-user", ['lists' => $user_result]), false, 'user');
            $row->column(3, $tab->withCard());
            $row->column(9, function (Column $column) {
                //订单监控数
                $headers = ["总订单数", "成功", "失败", "超时", "刷单", "总跑量"];
                $search = request()->all();
                $total_order_number = 0;
                $success_order_number = 0;
                $fail_order_number = 0;
                $overtime_order_number = 0;
                $refresh_order_number = 0;
                $total_order_amont = 0;
                $result = $this->groupCount($search);
                if (!empty($result)) {
                    foreach ($result as $k => $v) {
                        $total_order_number += $v['num'];
                        if ($v['status'] == 5) {
                            $success_order_number = $v['num'];
                            $total_order_amont = $v['total_amount'];
                        }
                        if ($v['status'] == 6) {
                            $fail_order_number = $v['num'];
                        }
                        if ($v['status'] == 4) {
                            $overtime_order_number = $v['num'];
                        }
                        if ($v['status'] == 2) {
                            $refresh_order_number = $v['num'];
                        }
                    }
                }
                $rows[] = [$total_order_number, '<span style="color: #597927">' . $success_order_number . '</span>', '<span style="color: #951D1D">' . $fail_order_number . '</span>', '<span style="color: #9B7D31">' . $overtime_order_number . '</span>', '<span style="color: #000B7B">' . $refresh_order_number . '</span>', '<span style="color: red">' . $total_order_amont . '</span>'];
                $table = new BobTable($headers, $rows);
                $box = Box::make('今日代收订单数监控【' . date('Y-m-d') . '】', $table->class("table custom-data-table data-table table-bordered complex-headers"));
                $column->row($box);
                $column->row('<h3 style="text-align: center;padding-bottom: 32px;padding-top: 32px">代收订单成功率监控</h3>');


                $model = new MerchantInfo();
                if (request('mid')) {
                    $model = $model->where('merchant_user_id', request('mid'));
                }
                $merchant_count = $model->count();
                $result = $model->orderBy("merchant_user_id", 'desc')->limit(10)->get(['merchant_user_id', 'coder', "name", 'currency_id']);
                $headers = ["商户名称", "总订单量", "成功", "失败", '超时', '刷单', "总跑量"];

                $data4 = [];
                if (!$result->isEmpty()) {
                    $search_4_item = request()->all();
                    foreach ($result as $item) {
                        $search_4_item['mid'] = $item->merchant_user_id;
                        $total_order_item_number_4 = 0;
                        $success_order_item_number_4 = 0;
                        $fail_order_item_number_4 = 0;
                        $overtime_order_item_number_4 = 0;
                        $refresh_order_item_number_4 = 0;
                        $total_order_item_amount_4 = 0;

                        $result_4_item = $this->groupCount($search_4_item);
                        if (!empty($result_4_item)) {
                            foreach ($result_4_item as $k => $v) {
                                $total_order_item_number_4 += $v['num'];
                                if ($v['status'] == 5) {
                                    $total_order_item_amount_4 = $v['total_amount'];
                                    $success_order_item_number_4 = $v['num'];
                                }
                                if ($v['status'] == 6) {
                                    $fail_order_item_number_4 = $v['num'];
                                }
                                if ($v['status'] == 4) {
                                    $overtime_order_item_number_4 = $v['num'];
                                }
                                if ($v['status'] == 2) {
                                    $refresh_order_item_number_4 = $v['num'];
                                }
                            }
                        }

                        $data4[] = [
                            "name" => $item->bname,
                            'total_order_number' => $total_order_item_number_4,
                            'success_order_number' => $this->formatData($success_order_item_number_4, $total_order_item_number_4, "#597927"),
                            'fail_order_number' => $this->formatData($fail_order_item_number_4, $total_order_item_number_4, "#951D1D"),
                            'overtime_order_number' => $this->formatData($overtime_order_item_number_4, $total_order_item_number_4, "#9B7D31"),
                            'refresh_order_number' => $this->formatData($refresh_order_item_number_4, $total_order_item_number_4, "#000B7B"),
                            'total_order_amount' => '<span style="color: red">' . $total_order_item_amount_4 . '</span>'
                        ];
                    }
                }
                $table4 = new BobTable($headers, $data4);
                $table4->view = "admin.home.merchant-order.table";
                $table4->withBorder();
                if ($merchant_count > 10) {
                    $table4->setFold(true);
                }
                $table4->setSearch(['page' => request('page', 1) + 1, 'channel_id' => request('channel_id', 0), 'user_id' => request('user_id', 0), 'payment_id' => request('payment_id', 0)]);
                $box4 = Box::make('今日代收订单统计【' . date('Y-m-d') . '】', $table4->class("table custom-data-table data-table table-bordered complex-headers"));
                $column->row($box4);
            });
        });
    }


    private function groupCount(array $data = []): array
    {
        $model = (new DepositOrder())->setConnection("centus");
        $model = $model->where('created_at', '>=', date('Y-m-d') . " 00:00:00")->where('created_at', '<=', date('Y-m-d') . " 23:59:59");
        $data = array_filter($data);
        if (isset($data['status'])) {
            $model = $model->where('status', $data['status']);
        }
        if (isset($data['mid'])) {
            $model = $model->where('mid', $data['mid']);
        }
        if (isset($data['channel_id'])) {
            $model = $model->where('channel_id', $data['channel_id']);
        }
        if (isset($data['payment_id'])) {
            $model = $model->where('payment_id', $data['payment_id']);
        }
        if (isset($data['user_id'])) {
            $model = $model->where('user_id', $data['user_id']);
        }
        $result = $model->select("status", DB::raw('count(*) as num'), DB::raw('sum(actual_amount) as total_amount'))->groupBy('status')->get();
        if (!$result->isEmpty()) {
            return $result->toArray();
        }
        return [];
    }

    private function formatData($number, $total, $color = ""): string
    {
        if ($number == 0) {
            return '<span style="color: ' . $color . '">' . $number . '<br/>0%</span>';
        }

        return '<span style="color: ' . $color . '">' . $number . '<br/>' . floatval(sprintf("%.4f", $number / $total) * 100) . "%" . '</span>';
    }

    public function merchantBenefit(Content $content): Content
    {
        $start = Carbon::today()->startOfDay();
        $end = Carbon::today()->endOfDay();

        $depositAgg = DB::connection('centus')->table('deposit_orders')
            ->selectRaw("
        mid,
        SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) AS deposit_success_order,
        SUM(CASE WHEN status = 5 THEN actual_amount ELSE 0 END) AS deposit_success_amount,
        SUM(CASE WHEN status = 5 THEN merchant_fee + merchant_extra_fee ELSE 0 END) AS deposit_merchant_fee
    ")
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('mid');

        $transferAgg = DB::connection('centus')->table('transfer_orders')
            ->selectRaw("
        mid,
        SUM(CASE WHEN type = 0 AND status = 4 THEN 1 ELSE 0 END) AS transfer_success_order,
        SUM(CASE WHEN type = 0 AND status = 4 THEN actual_amount ELSE 0 END) AS transfer_success_amount,
        SUM(CASE WHEN type = 0 AND status = 4 THEN merchant_fee + merchant_extra_fee ELSE 0 END) AS transfer_merchant_fee,
        SUM(CASE WHEN type = 1 AND status = 4 THEN 1 ELSE 0 END) AS settlement_success_order,
        SUM(CASE WHEN type = 1 AND status = 4 THEN actual_amount ELSE 0 END) AS settlement_success_amount,
        SUM(CASE WHEN type = 1 AND status = 4 THEN merchant_fee + merchant_extra_fee ELSE 0 END) AS settlement_merchant_fee
    ")
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('mid');

        $balanceAgg = DB::connection('centus')->table('merchant_balance_logs')
            ->selectRaw("
        mid,
        SUM(CASE WHEN type IN (5,11) THEN amount ELSE 0 END) AS amount1,
        SUM(CASE WHEN type = 12 THEN amount ELSE 0 END) AS amount2
    ")
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('mid');


        $grid = Grid::make(MerchantInfo::on('centus'), function (Grid $grid)use($depositAgg,$transferAgg,$balanceAgg) {
            $grid->model()->leftJoinSub($depositAgg, 'd', 'd.mid', '=', 'merchant_infos.merchant_user_id');
            $grid->model()->leftJoinSub($transferAgg, 't', 't.mid', '=', 'merchant_infos.merchant_user_id');
            $grid->model()->leftJoinSub($balanceAgg, 'b', 'b.mid', '=', 'merchant_infos.merchant_user_id');
            $grid->model()->select([
                'merchant_infos.merchant_user_id',
                'merchant_infos.name',
                'merchant_infos.coder',
                DB::raw('COALESCE(d.deposit_success_order,0) AS deposit_success_order'),
                DB::raw('COALESCE(d.deposit_success_amount,0) AS deposit_success_amount'),
                DB::raw('COALESCE(d.deposit_merchant_fee,0) AS deposit_merchant_fee'),
                DB::raw('COALESCE(t.transfer_success_order,0) AS transfer_success_order'),
                DB::raw('COALESCE(t.transfer_success_amount,0) AS transfer_success_amount'),
                DB::raw('COALESCE(t.transfer_merchant_fee,0) AS transfer_merchant_fee'),
                DB::raw('COALESCE(t.settlement_success_order,0) AS settlement_success_order'),
                DB::raw('COALESCE(t.settlement_success_amount,0) AS settlement_success_amount'),
                DB::raw('COALESCE(t.settlement_merchant_fee,0) AS settlement_merchant_fee'),
                DB::raw('COALESCE(b.amount1,0) AS amount1'),
                DB::raw('COALESCE(b.amount2,0) AS amount2'),
                DB::raw('(COALESCE(d.deposit_merchant_fee,0) + COALESCE(t.transfer_merchant_fee,0)) AS amount3'),
            ]);

                $grid->column("merchant_name", "商户代码")->display(function () {
                    return "#【" . $this->merchant_user_id . "】【" . $this->coder . "】" . $this->name;
                });
                $grid->column("deposit_success_order", "代收成功单数");
                $grid->column("deposit_success_amount", "代收总跑量");
                $grid->column("deposit_merchant_fee", "代收手续费");
                $grid->column("transfer_success_order", "代付成功单数");
                $grid->column("transfer_success_amount", "代付金额");
                $grid->column("transfer_merchant_fee", "代付手续费");
                $grid->column("settlement_success_order", "结算成功单数");
                $grid->column("settlement_success_amount", "结算金额");
                $grid->column("settlement_merchant_fee", "结算手续费");
                $grid->column("amount1", "增项+冲正");
                $grid->column("amount2", "减项资金");
                $grid->column("amount3", "总手续费");
                $grid->disableRowSelector();
                $grid->disableActions();
                $grid->disableCreateButton();
                $grid->paginate(10);
                $grid->async();
                $grid->filter(function (Filter $filter) {
                    $filter->equal('merchant_user_id', '商户ID')->width(3);
                    $filter->expand();
                    $filter->panel();
                    $filter->like('name', "商户名称")->width(3);
                    $filter->like('coder', "商户代码")->width(3);
                });
            });
        return $content->title("商户成效")->body($grid);
    }

    public function channelBenefit(Content $content): Content
    {
        $start = Carbon::today()->startOfDay();
        $end = Carbon::today()->endOfDay();

        $depositAgg = DB::connection('centus')->table('deposit_orders')
            ->selectRaw("
        channel_id,
        SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) AS deposit_order,
        SUM(CASE WHEN status = 5 THEN actual_amount ELSE 0 END) AS deposit_amount
    ")
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('channel_id');

        $transferAgg = DB::connection('centus')->table('transfer_orders')
            ->selectRaw("
        channel_id,
        SUM(CASE WHEN type = 0 AND status = 4 THEN 1 ELSE 0 END) AS transfer_order,
        SUM(CASE WHEN type = 0 AND status = 4 THEN actual_amount ELSE 0 END) AS transfer_amount
    ")
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('channel_id');


        $grid = Grid::make(Channel::on('centus'), function (Grid $grid)use($depositAgg,$transferAgg) {
            $grid->model()->leftJoinSub($depositAgg, 'd', 'd.channel_id', '=', 'channels.id');
            $grid->model()->leftJoinSub($transferAgg, 't', 't.channel_id', '=', 'channels.id');
            $grid->model()->select([
                'channels.id',
                'channels.code',
                'channels.name',
                DB::raw('COALESCE(d.deposit_order, 0)  AS deposit_order'),
                DB::raw('COALESCE(d.deposit_amount, 0) AS deposit_amount'),
                DB::raw('COALESCE(t.transfer_order, 0)  AS transfer_order'),
                DB::raw('COALESCE(t.transfer_amount, 0) AS transfer_amount'),
            ]);

            $grid->column("channel_name", "渠道名称")->display(function () {
                return "#【" . $this->id . "】【" . $this->code . "】" . $this->name;
            });
            $grid->column("deposit_order", "代收成功单数");
            $grid->column("deposit_amount", "代收跑量");
            $grid->column("transfer_order", "代付成功单数");
            $grid->column("transfer_amount", "代付跑量");
            $grid->disableRowSelector();
            $grid->disableActions();
            $grid->async();
            $grid->disableCreateButton();
            $grid->paginate(10);

            $grid->filter(function (Filter $filter) {
                $filter->expand();
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->like('name', "渠道名称")->width(3);
            });

        });
        return $content->title("渠道成效")->body($grid);
    }

    public function userBenefit(Content $content): Content
    {

        $begin = Carbon::today()->startOfDay();
        $end = Carbon::today()->endOfDay();

        // ===== ① 代收聚合：一次性统计 “金额/单数/各级佣金”
        $depositSub = DB::connection('centus')->table('deposit_orders')
            ->selectRaw("
                user_id,
                COUNT(*)                                           AS deposit_order,
                COALESCE(SUM(actual_amount),0)                     AS deposit_amount,
                COALESCE(SUM(user_commission),0)                   AS dep_user_commission,
                COALESCE(SUM(user_agent1_commission),0)            AS dep_a1,
                COALESCE(SUM(user_agent2_commission),0)            AS dep_a2,
                COALESCE(SUM(user_agent3_commission),0)            AS dep_a3,
                COALESCE(SUM(user_agent4_commission),0)            AS dep_a4,
                COALESCE(SUM(user_agent5_commission),0)            AS dep_a5
            ")
            ->whereBetween('created_at', [$begin, $end])
            ->where('status', 5)
            ->where('user_id', '>', 0)
            ->groupBy('user_id');

        // ===== ② 代付聚合：一次性统计 “金额/单数/各级佣金”
        $transferSub = DB::connection('centus')->table('transfer_orders')
            ->selectRaw("
                user_id,
                COUNT(*)                                           AS transfer_order,
                COALESCE(SUM(actual_amount),0)                     AS transfer_amount,
                COALESCE(SUM(user_commission),0)                   AS trans_user_commission,
                COALESCE(SUM(user_agent1_commission),0)            AS trans_a1,
                COALESCE(SUM(user_agent2_commission),0)            AS trans_a2,
                COALESCE(SUM(user_agent3_commission),0)            AS trans_a3,
                COALESCE(SUM(user_agent4_commission),0)            AS trans_a4,
                COALESCE(SUM(user_agent5_commission),0)            AS trans_a5
            ")
            ->whereBetween('created_at', [$begin, $end])
            ->where('status', 4)
            ->where('user_id', '>', 0)
            ->groupBy('user_id');

        // ===== ③ 余额变动聚合：一次性算出 2/3/5/6/8/9 各类型的绝对值合计
        $balanceSub = DB::connection('centus')->table('user_balance_logs')
            ->selectRaw("
                user_id,
                ABS(COALESCE(SUM(CASE WHEN type=2 THEN amount END),0)) AS reduce_commission,
                ABS(COALESCE(SUM(CASE WHEN type=3 THEN amount END),0)) AS add_commission,
                ABS(COALESCE(SUM(CASE WHEN type=5 THEN amount END),0)) AS reduce_deposit,
                ABS(COALESCE(SUM(CASE WHEN type=6 THEN amount END),0)) AS add_deposit,
                ABS(COALESCE(SUM(CASE WHEN type=8 THEN amount END),0)) AS reduce_transfer,
                ABS(COALESCE(SUM(CASE WHEN type=9 THEN amount END),0)) AS add_transfer
            ")
            ->whereBetween('created_at', [$begin, $end])
            ->groupBy('user_id');


        $grid = Grid::make(UserModel::on('centus')->leftJoinSub($depositSub, 'd', 'd.user_id', '=', 'users.id')
            ->leftJoinSub($transferSub, 't', 't.user_id', '=', 'users.id')
            ->leftJoinSub($balanceSub, 'b', 'b.user_id', '=', 'users.id')->addSelect([
                'users.*',
                DB::raw('COALESCE(d.deposit_order,0)         AS deposit_order'),
                DB::raw('COALESCE(d.deposit_amount,0)        AS deposit_amount'),
                DB::raw('COALESCE(t.transfer_order,0)        AS transfer_order'),
                DB::raw('COALESCE(t.transfer_amount,0)       AS transfer_amount'),

                DB::raw('COALESCE(d.dep_user_commission,0)   AS user_deposit_profit'),
                DB::raw('COALESCE(t.trans_user_commission,0) AS user_transfer_profit'),

                DB::raw('(COALESCE(d.dep_a1,0)+COALESCE(t.trans_a1,0)) AS one_agent_profit'),
                DB::raw('(COALESCE(d.dep_a2,0)+COALESCE(t.trans_a2,0)) AS two_agent_profit'),
                DB::raw('(COALESCE(d.dep_a3,0)+COALESCE(t.trans_a3,0)) AS three_agent_profit'),
                DB::raw('(COALESCE(d.dep_a4,0)+COALESCE(t.trans_a4,0)) AS forth_agent_profit'),
                DB::raw('(COALESCE(d.dep_a5,0)+COALESCE(t.trans_a5,0)) AS five_agent_profit'),

                DB::raw('COALESCE(b.reduce_commission,0)     AS reduce_commission'),
                DB::raw('COALESCE(b.add_commission,0)        AS add_commission'),
                DB::raw('COALESCE(b.reduce_deposit,0)        AS reduce_deposit'),
                DB::raw('COALESCE(b.add_deposit,0)           AS add_deposit'),
                DB::raw('COALESCE(b.reduce_transfer,0)       AS reduce_transfer'),
                DB::raw('COALESCE(b.add_transfer,0)          AS add_transfer'),

                DB::raw('(COALESCE(d.dep_user_commission,0)+COALESCE(t.trans_user_commission,0)) AS total_user_commission')
            ]), function (Grid $grid) use ($begin, $end) {
            $userIds = DepositOrder::on("centus")->select("user_id")->where('created_at', '>=', $begin)->where('created_at', '<=', $end)->where('status', 5)->where('user_id', '>', 0)->groupBy('user_id');
            $grid->model()->where('is_agent', 0)->whereIn('id', $userIds);

            $grid->column("user_name", "金主号")->display(function () {
                return "#【" . $this->id . "】" . $this->name;
            });
            $grid->column("deposit_amount_number", "代收总跑量/代收成功单数")->display(function () {
                return $this->deposit_amount . "/" . $this->deposit_order;
            });
            $grid->column("user_deposit_profit", "代收佣金");
            $grid->column("transfer_amount_number", "代付总跑量/代付成功单数")->display(function () {
                return $this->transfer_amount . "/" . $this->transfer_order;
            });
            $grid->column("user_transfer_profit", "代付佣金");
            $grid->column("total_user_commission", "总佣金");
            $grid->column("one_agent_profit", "一级代理收入");
            $grid->column("two_agent_profit", "二代理收入");
            $grid->column("three_agent_profit", "三级代理收入");
            $grid->column("forth_agent_profit", "四级代理收入");
            $grid->column("five_agent_profit", "五级代理收入");
            $grid->column("reduce_commission", "佣金减项");
            $grid->column("add_commission", "佣金加项");
            $grid->column("reduce_deposit", "代收减项");
            $grid->column("add_deposit", "代收加项");
            $grid->column("reduce_transfer", "代付减项");
            $grid->column("add_transfer", "代付加项");
            $grid->disableRowSelector();
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->async();
            $grid->paginate(10);

            $grid->filter(function (Filter $filter) {
                $filter->equal('id')->width(3);
                $filter->expand();
                $filter->panel();
                $filter->like('name', "金主名称")->width(3);
            });
        });
        return $content->title("金主成效")->body($grid);
    }

    public function bankBenefit(Content $content): Content
    {

        $begin_date = Carbon::today()->startOfDay();
        $end_date = Carbon::today()->endOfDay();


        $depositSub = DB::connection('centus')->table('deposit_orders')
            ->selectRaw("
                user_id,
                COUNT(*) AS deposit_order_total,
                SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) AS deposit_order_success,
                COALESCE(SUM(CASE WHEN status = 5 THEN actual_amount END), 0) AS deposit_number_amount
            ")
            ->whereBetween('created_at', [$begin_date, $end_date])
            ->where('user_id', '>', 0)
            ->groupBy('user_id');

        $grid = Grid::make(UserModel::on('centus')->leftJoinSub($depositSub, 'd', 'd.user_id', '=', 'users.id')->addSelect([
            'users.id',
            'users.name',
            DB::raw('COALESCE(d.deposit_order_total,0) AS deposit_order_total'),
            DB::raw('COALESCE(d.deposit_order_success,0) AS deposit_order_success'),
            DB::raw('COALESCE(d.deposit_number_amount,0) AS deposit_number_amount'),
        ]), function (Grid $grid) use ($begin_date, $end_date) {
            $userIds = DepositOrder::on("centus")->select("user_id")->where('created_at', '>=', $begin_date)->where('created_at', '<=', $end_date)->where('status', 5)->where('user_id', '>', 0)->groupBy('user_id');
            $grid->model()->where('is_agent', 0)->whereIn('id', $userIds);

            $grid->column("user_name", "金主号")->display(function () {
                return "#【" . $this->id . "】" . $this->name;
            })->width("10%");
            $grid->column("name", "上号名称")->display(function () {
                return '-';
            })->width("10%");
            $grid->column("deposit_order_total", "代收总单数")->width("10%");
            $grid->column("deposit_order_success", "代收成功单数")->width("10%");
            $grid->column("deposit_number_amount", "代收总跑量")->width("10%");
            $grid->column("detail", "操作")->display("详情")->expand(function () use ($begin_date, $end_date) {
                $header = [];
                $rows[] = [];
                $user_bank_result = DepositOrder::on("centus")->select(
                    'user_bank_id',
                    DB::raw('COUNT(*) as total_count'),
                    DB::raw('SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) as success_count'),
                    DB::raw('SUM(actual_amount) as total_amount')
                )->where('created_at', '>=', $begin_date)->where('created_at', '<=', $end_date)->where('user_id', $this->id)->where('user_bank_id', '>', 0)->groupBy('user_bank_id')->get();
                if (!$user_bank_result->isEmpty()) {
                    foreach ($user_bank_result as $item) {
                        $rows[] = [
                            '-',
                            optional(App::make(GetUserBankDetailService::class)->excute($item->user_bank_id))->offsetGet('bname') ?: $item->user_bank_id,
                            $item->total_count,
                            $item->success_count,
                            $item->total_amount,
                            '-'
                        ];
                    }
                }
                $table = new BobTable($header, $rows);
                $table->withBorder();
                $table->setWith("10%");
                return $table;
            })->width("10%");


            $grid->disableRowSelector();
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->withBorder();
            $grid->async();
            $grid->paginate(10);

            $grid->filter(function (Filter $filter) use ($begin_date, $end_date) {
                $filter->expand();
                $filter->panel();
                $filter->equal('id')->width(3);
                $filter->like('name', "金主名称")->width(3);
            });
        });
        return $content->title("上号成效")->body($grid);
    }


}

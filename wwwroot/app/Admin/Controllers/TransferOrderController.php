<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\BankCode;
use App\Models\AgentUser;
use Dcat\Admin\Layout\Row;
use App\Models\UserRelation;
use App\Models\TransferOrder;
use Dcat\Admin\Layout\Column;
use App\Models\AgentUserRelation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Admin\Extensions\Tools\AutoRefresh;
use App\Admin\Actions\Grid\TransferOrder\Copy;
use App\Admin\Actions\Grid\TransferOrder\Logs;
use App\Services\Cache\User\GetUserListService;
use App\Services\Common\DataFormatBnameService;
use App\Services\Common\GetBankCodeListService;
use App\Services\Cache\CacheConstPrefixService;
use App\Admin\Metrics\Admin\TransferOrder\Card1;
use App\Admin\Metrics\Admin\TransferOrder\Card2;
use App\Admin\Metrics\Admin\TransferOrder\Card3;
use App\Admin\Metrics\Admin\TransferOrder\Card4;
use App\Admin\Metrics\Admin\TransferOrder\Card5;
use App\Admin\Metrics\Admin\TransferOrder\Card6;
use App\Admin\Actions\Grid\TransferOrder\Channel;
use App\Services\Cache\User\GetUserDetailService;
use App\Admin\Actions\Grid\TransferOrder\OrderFail;
use App\Admin\Actions\Grid\TransferOrder\OrderInfo;
use App\Admin\Actions\Grid\TransferOrder\ExportData;
use App\Admin\Actions\Grid\TransferOrder\OrderCorre;
use App\Admin\Actions\Grid\TransferOrder\QueryProof;
use App\Services\Cache\User\GetUserAgentListService;
use App\Services\Cache\Channel\GetChannelListService;
use App\Admin\Actions\Grid\TransferOrder\OrderSuccess;
use App\Admin\Actions\Grid\TransferOrder\BatchCallback;
use App\Admin\Actions\Grid\TransferOrder\BatchFailAction;
use App\Admin\Actions\Grid\TransferOrder\CallbackRecords;
use App\Admin\Actions\Grid\TransferOrder\MerchantCallback;
use App\Admin\Actions\Grid\TransferOrder\QueryOrderStatus;
use App\Admin\Actions\Grid\TransferOrder\BatchTransferAction;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;

class TransferOrderController extends CommonController
{
    protected $disableEdit = true;

    protected $disableCreate = true;

    protected function grid(): Grid
    {
        $createdAt = request('created_at');
        $beginDate = $createdAt['start'] ?? date('Y-m-d') . " 00:00:00";
        $endDate = $createdAt['end'] ?? date('Y-m-d') . " 23:59:59";
        $merchantBaseInfoService = App::make(CacheMerchantBaseInfoService::class);
        $userDetailService = App::make(GetUserDetailService::class);
        $channelOptions = collect(App::make(GetChannelListService::class)->excute())->pluck('bname', 'id');
        $currencyOptions = collect(App::make(DataFormatBnameService::class)->excute(config('default.currency')))->pluck('bname', 'id');
        $bankCodeOptions = App::make(GetBankCodeListService::class)->excute();
        $merchantAgentOptions = bob_build_select_options(App::make(GetMerchantAgentListService::class)->excute());
        $userAgentOptions = bob_build_select_options(App::make(GetUserAgentListService::class)->excute());
        $userOptions = config('app.name') == 'sgpay' ? collect(App::make(GetUserListService::class)->excute())->pluck('bname', 'id') : null;

        $query = TransferOrder::with([
            'merchant_info' => function ($query) {
                $query->select(['merchant_user_id', 'name', 'coder', 'currency_id'])->withTrashed();
            },
            'admin' => function ($query) {
                $query->select(['id', 'name', 'username']);
            },
            'channel' => function ($query) {
                $query->select(['id', 'name', 'transfer_order_query', 'classname']);
            },
            'user' => function ($query) {
                $query->select(['id', 'name']);
            },
        ])->select($this->listColumns());

        return Grid::make($query, function (Grid $grid) use ($beginDate, $endDate, $merchantBaseInfoService, $userDetailService, $channelOptions, $currencyOptions, $bankCodeOptions, $merchantAgentOptions, $userAgentOptions, $userOptions) {

            $adminUser = Admin::user();
            $canBatchCallback = $adminUser->can('transfer-order-batch-callback');
            $canBatchSubmit = $adminUser->can('transfer-order-batch-submit');
            $canBatchFail = $adminUser->can('transfer-order-batch-fail');
            $canManualSuccess = $adminUser->can('transfer-order-manual-success');
            $canManualFail = $adminUser->can('transfer-order-manual-fail');
            $canCorre = $adminUser->can('transfer-order-corre');
            $canChannel = $adminUser->can('transfer-order-channel');
            $canCallback = $adminUser->can('transfer-order-callback');

            $grid->model()->where('type', 0);
            $grid->column('id')->sortable();
            $grid->column('channel_info', '渠道信息')->display(function () {
                if($this->channel_id > 0){
                    if($this->user_id > 0){
                        $user = App::make(GetUserDetailService::class)->excute($this->user_id);
                        if(!empty($user)){
                            $data[] = ["金主",optional($user)->offsetGet('bname')];
                            $data[] = ["金主费率",(floatval($this->user_rate)*100)."%"];
                            $data[] = ["金主佣金",bob_unit_format($this->user_commission)];
                        }
                        if($this->user_agent1_id > 0 ){
                            $agent1 = App::make(GetUserDetailService::class)->excute($this->user_agent1_id);
                            $data[] = ["金主一级代理",optional($agent1)->offsetGet('bname')];
                            $data[] = ["金主一级代理费率",(floatval($this->user_agent1_rate)*100)."%"];
                            $data[] = ["金主一级代理佣金",bob_unit_format($this->user_agent1_commission)];
                        }
                        if($this->user_agent2_id > 0){
                            $agent2 = App::make(GetUserDetailService::class)->excute($this->user_agent2_id);
                            $data[] = ["金主二级代理",optional($agent2)->offsetGet('bname')];
                            $data[] = ["金主二级代理费率",(floatval($this->user_agent2_rate)*100)."%"];
                            $data[] = ["金主二级代理佣金",bob_unit_format($this->user_agent2_commission)];
                        }
                        if($this->user_agent3_id > 0){
                            $agent3 = App::make(GetUserDetailService::class)->excute($this->user_agent3_id);
                            $data[] = ["金主三级代理",optional($agent3)->offsetGet('bname')];
                            $data[] = ["金主三级代理费率",(floatval($this->user_agent3_rate)*100)."%"];
                            $data[] = ["金主三级代理佣金",bob_unit_format($this->user_agent3_commission)];
                        }
                        if($this->user_agent4_id > 0){
                            $agent4 = App::make(GetUserDetailService::class)->excute($this->user_agent4_id);
                            $data[] = ["金主四级代理",optional($agent4)->offsetGet('bname')];
                            $data[] = ["金主四级代理费率",(floatval($this->user_agent4_rate)*100)."%"];
                            $data[] = ["金主四级代理佣金",bob_unit_format($this->user_agent4_commission)];
                        }
                        if($this->user_agent5_id > 0){
                            $agent5 = App::make(GetUserDetailService::class)->excute($this->user_agent5_id);
                            $data[] = ["金主五级代理",optional($agent5)->offsetGet('bname')];
                            $data[] = ["金主五级代理费率",(floatval($this->user_agent5_rate)*100)."%"];
                            $data[] = ["金主五级代理佣金",bob_unit_format($this->user_agent5_commission)];
                        }
                        return bob_show_table_info($data);
                    }
                    if($this->channel_id > 0){
                        $data[] = ["渠道名称","【#".$this->channel_id."】".$this->channel->name];
                        if($this->channel_ordernumber)$data[] = ["渠道单号",$this->channel_ordernumber];
                        return bob_show_table_info($data);
                    }
                }
                return '-';
            });
            $grid->column('ordernumer_info', '商户订单号/平台订单号')->display(function () {
                $data[] = ["商户单号", $this->order_no];
                $data[] = ["平台单号", $this->ordernumber];
                return bob_show_table_info($data);
            });
            $grid->column('merchant_info.bname', '四方商户');
            $grid->column('currency_id','币种')->display(function (){
                return optional(collect(config('default.currency'))->where('id',$this->currency_id)->first())->offsetGet('name');
            });
            $grid->column('status', '订单状态')->display(function () {
                $data[] = ["订单状态",bob_show_label(optional(config('default.transfer_status'))[$this->status], $this->status, 3).($this->hand_success == 1 ? '【补】' : '')];
                if($this->user_id > 0 && $this->status == 2){
                    $data[]  = ["支付状态",bob_show_label(optional(config('default.transfer_pay_status'))[$this->pay_status], $this->pay_status)];
                }
                return bob_show_table_info($data);
            });
            $grid->column('callback_info', "推送信息")->display(function () {
                if ($this->status == 4 || $this->status == 5) {
                    $data[] = ["推送次数",$this->callback_count];
                    if($this->callback_time > 0){
                        $data[] = ["推送时间",date('Y-m-d H:i:s',$this->callback_time)];
                    }
                    if($this->callback_status == 1){
                        $data[] = ["推送状态",'<span class="label bg-success margin-r-5">推送成功</span>'];
                    }
                    if($this->callback_status == 2){
                        $data[] = ["推送状态",'<span class="label bg-red margin-r-5">推送失败</span>'];
                    }
                    return bob_show_table_info($data);
                }
                return;
            });
            $grid->column('amount_info', "金额信息")->display(function () {
                $data[] = ["订单金额", bob_unit_format($this->amount)];
                $data[] = ["实付金额", bob_unit_format($this->actual_amount)];
                if ($this->child_count > 0) {
                    $data[] = ["子订单数", $this->child_count];
                }
                return bob_show_table_info($data);
            });
            $grid->column('merchant_fee', "手续费")->display(function ($value) {
                $data[] = ['商户费率',(floatval($this->merchant_rate)*100)."%"];
                $data[] = ['商户手续费',bob_unit_format($value)];
                $data[] = ['商户额外手续费',bob_unit_format($this->merchant_extra_fee)];
                if($this->merchant_agent1_id > 0){
                    $agent_user = AgentUser::select(['id','name'])->find($this->merchant_agent1_id);
                    if($agent_user){
                        $data[] = ['商户一级代理',optional($agent_user)->offsetGet('name')];
                        $data[] = ['商户一级代理费率',(floatval($this->merchant_agent1_rate)*100)."%"];
                        $data[] = ['商户一级代理佣金',bob_unit_format($this->merchant_agent1_commission)];

                        $agent_user_parent = $agent_user->getAncestors();
                        if(!empty($agent_user_parent)){
                            if(count($agent_user_parent) >= 1 && $this->merchant_agent2_id > 0){
                                $data[] = ['商户二级代理',optional($agent_user_parent->firstWhere('id',$this->merchant_agent2_id))->offsetGet('name')];
                                $data[] = ['商户二级代理费率',(floatval($this->merchant_agent2_rate)*100)."%"];
                                $data[] = ['商户二级代理佣金',bob_unit_format($this->merchant_agent2_commission)];
                            }
                            if(count($agent_user_parent) >= 2 && $this->merchant_agent3_id > 0){
                                $data[] = ['商户三级代理',optional($agent_user_parent->firstWhere('id',$this->merchant_agent3_id))->offsetGet('name')];
                                $data[] = ['商户三级代理费率',(floatval($this->merchant_agent3_rate)*100)."%"];
                                $data[] = ['商户三级代理佣金',bob_unit_format($this->merchant_agent3_commission)];
                            }
                        }
                    }
                }
                if($this->status == 4){
                    $data[] = ['渠道成本',bob_unit_format($this->channel_cost)];
                    $data[] = ['系统利润',bob_unit_format($this->profit)];
                }
                return bob_show_table_info($data,[],[],3);
            });
            $grid->column('collection_info', "收款信息")->display(function ($value) {
                $bank_name = $this->bank_name;
                if (empty($bank_name)) {
                    $bank_name = optional(BankCode::where('id', $this->bank_id)->first())->name;
                }
                $data[] = ["收款银行", $bank_name];
                $data[] = ["银行代码", $this->bank_code];
                if($this->holder_name)$data[] = ["银行卡户名", $this->holder_name];
                if($this->card_no)$data[] = ["银行卡卡号", $this->card_no];
                if($this->bank_province)$data[] = ["开户行省份", $this->bank_province];
                if($this->bank_city)$data[] = ["开户行城市", $this->bank_city];
                if($this->bank_branch)$data[] = ["银行分行地址", $this->bank_branch];
                if($this->utr)$data[] = ["UTR",$this->utr];
                return bob_show_table_info($data);
            });
            $grid->column('certificate_info', "汇款回单")->display(function ($value) {
                $data = [];
                if (!empty($this->pay_certificate_1)) $data[] = ['<a href="' . Storage::disk('admin')->url($this->pay_certificate_1) . '" target="_blank">带公章回执单</a>'];
                if (!empty($this->pay_certificate_2)) $data[] = ['<a href="' . Storage::disk('admin')->url($this->pay_certificate_2) . '" target="_blank">带完整卡号回执单</a>'];
                if (!empty($this->pay_certificate_3)) $data[] = ['<a href="' . Storage::disk('admin')->url($this->pay_certificate_3) . '" target="_blank">银行流水明细</a>'];
                if (!empty($data)) return bob_show_table_info($data);
                return;
            });
            $grid->column('success_time', "成功时间")->display(function ($value) {
                if ($this->status == 4) {
                    return date('Y-m-d H:i:s', $value);
                }
                return;
            });
            $grid->column('created_at', "订单时间");
            $grid->column('admin.username', "操作人");
            $grid->column('remark', "备注")->limit(30);
            $grid->disableEditButton();
            $grid->disableDeleteButton();
            $grid->disableCreateButton();
            $grid->batchActions(function (Grid\Tools\BatchActions $batch) {
                $batch->disableDelete();
            });
            $grid->showRowSelector();
            $grid->tools(function (Grid\Tools $tools) use ($canBatchCallback, $canBatchSubmit, $canBatchFail) {
                $tools->append(new AutoRefresh('transfer-orders'));
                if ($canBatchCallback) {
                    $tools->append(new BatchCallback());
                }
                if ($canBatchSubmit) {
                    $tools->append(new BatchTransferAction());
                }
                if ($canBatchFail) {
                    $tools->append(new BatchFailAction());
                }
                $tools->append(new ExportData());
            });
            $grid->actions(function ($actions) use ($canManualSuccess, $canManualFail, $canCorre, $canChannel, $canCallback) {
                if ($canChannel && $actions->row['status'] == 3) {
                    $actions->append(new Channel());
                }
                $actions->append(new Logs());
                $actions->append(new CallbackRecords());

                if ($canCallback && ($actions->row['status'] == 4 || $actions->row['status'] == 5)) {
                    $actions->append(new MerchantCallback());
                }

                if ($canCorre && $actions->row['status'] == 4) {
                    $actions->append(new OrderCorre());
                }

                if($canManualSuccess && ($actions->row['status'] == 2 || $actions->row['status'] == 1  || $actions->row['status'] == 7  || $actions->row['status'] == 3)){
                    $actions->append(new OrderSuccess());
                }

                if ($canManualFail && ($actions->row['status'] == 2 || $actions->row['status'] == 6 || $actions->row['status'] == 1|| $actions->row['status'] == 7)) {
                    $actions->append(new OrderFail());
                }
                if($canManualFail && $actions->row['status'] == 3){
                    if(!Cache::has(CacheConstPrefixService::HANDLE_DO_TRANSFER_ACTION.$actions->row['id'])){
                        $actions->append(new OrderFail());
                    }
                }
                if($actions->row['status'] == 2 && $actions->row['channel'] && $actions->row['channel']['transfer_order_query'] == 1){
                    $actions->append(new QueryOrderStatus());
                }
                $actions->append(new Copy());

                if(config('app.name') == 'sgpay' && $actions->row['status'] == 4 && ($actions->row['channel_id'] == 157 || $actions->row['channel_id'] == 161)){
                    $actions->append(new QueryProof());
                }

                if ((int) ($actions->row['currency_id'] ?? 0) === 5) {
                    $actions->append(new OrderInfo());
                }
            });

            $grid->header(function () use ($beginDate, $endDate) {
                $row = new Row();
                $row->column(12, function (Column $column) use ($row, $beginDate, $endDate) {
                    $row->column(4, new Card1(request()->all(), $beginDate, $endDate));
                    $row->column(4, new Card2(request()->all(), $beginDate, $endDate));
                    $row->column(4, new Card3(request()->all(), $beginDate, $endDate));
                    $row->column(4, new Card4(request()->all(), $beginDate, $endDate));
                    $row->column(4, new Card5(request()->all(), $beginDate, $endDate));
                    $row->column(4, new Card6(request()->all(), $beginDate, $endDate));
                });
                return $row;
            });


            $grid->filter(function (Grid\Filter $filter) use ($beginDate, $endDate, $merchantBaseInfoService, $userDetailService, $channelOptions, $currencyOptions, $bankCodeOptions, $merchantAgentOptions, $userAgentOptions, $userOptions) {
                if (request('created_at') === null) {
                    request()->merge(['created_at' => ['start' => $beginDate, 'end' => $endDate]]);
                }
                $filter->expand();
                $filter->panel();
                $filter->equal('id','订单编号')->width(3);
                $filter->equal('mid',"商户")->select(function ($mid) use ($merchantBaseInfoService) {
                    if ($mid) {
                        $result = $merchantBaseInfoService->excute($mid);

                        return empty($result) ? [] : [$result['merchant_user_id'] => $result['bname']];
                    }

                    return [];
                })->ajax("/ajax/getMerchantList","merchant_user_id","bname")->width(3);
                $filter->equal("ordernumber", "平台单号")->width(3);
                $filter->equal("order_no", "商户单号")->width(3);
                $filter->equal("channel_ordernumber", "渠道单号")->width(3);
                $filter->equal('channel_id',"支付渠道")->select($channelOptions)->width(3);
                $filter->equal('hand_success', "人工补单")->select([0 => '否', 1 => "是"])->width(3);
                $filter->equal('status', "订单状态")->select(config('default.transfer_status'))->width(3);
                $filter->between('created_at', "创建时间")->datetime()->width(3)->default(['start' => $beginDate, 'end' => $endDate]);
                $filter->equal('currency_id',"请选择币种")->select($currencyOptions)->width(3);
                $filter->whereBetween('success_time', function ($q) {
                    $start = $this->input['start'] ?? null;
                    $end = $this->input['end'] ?? null;
                    if ($start !== null) {
                        $q->where('success_time', '>=', strtotime($start));
                    }
                    if ($end !== null) {
                        $q->where('success_time', '<=', strtotime($end));
                    }
                }, "成功时间")->datetime()->width(3);
                $filter->equal("bank_id","银行代码")->select($bankCodeOptions)->width(3);
                $filter->equal('holder_name', "收款人")->width(3);
                $filter->equal('card_no', "收款卡号")->width(3);
                $filter->equal('amount', "提交金额")->width(3);
                $filter->equal('actual_amount', "实付金额")->width(3);
                $filter->where('merchant_agent_id', function ($query) {
                    $query->whereIn('merchant_agent1_id', AgentUserRelation::query()->select('child_id')->where('parent_id', $this->input));
                },'商户代理')->select($merchantAgentOptions)->width(3);
                if (config('app.name') == "sgpay") {
                    $filter->equal('user_id',"金主")->select($userOptions)->width(3);
                } else {
                    $filter->equal('user_id',"金主")->select(function ($user_id) use ($userDetailService) {
                        if ($user_id) {
                            $result = $userDetailService->excute($user_id);

                            return empty($result) ? [] : [$result['id'] => $result['bname']];
                        }

                        return [];
                    })->ajax("ajax/getUserList","id","bname")->width(3);
                }
                $filter->where('user_agent_id', function ($query) {
                    $query->whereIn('user_id', UserRelation::query()->select('child_id')->where('parent_id', $this->input));
                },'金主代理')->select($userAgentOptions)->width(3);
                $filter->equal('callback_status', "回调状态")->select([0 => "未回调",1 => "回调成功",2 => "回调失败"])->width(3);
                $filter->where('amount_min', function ($query) {
                    if ($this->input !== '' && $this->input !== null) {
                        $query->where('amount', '>=', $this->input);
                    }
                }, '最小金额')->width(3);

                $filter->where('amount_max', function ($query) {
                    if ($this->input !== '' && $this->input !== null) {
                        $query->where('amount', '<=', $this->input);
                    }
                }, '最大金额')->width(3);
            });
        });
    }

    private function listColumns(): array
    {
        return [
            'id', 'type', 'channel_id', 'channel_ordernumber', 'user_id', 'order_no', 'ordernumber', 'hand_success', 'hand_admin_id', 'callback_time',
            'amount', 'actual_amount', 'success_time', 'created_at', 'merchant_rate', 'merchant_extra_fee', 'merchant_agent1_id', 'merchant_agent1_rate',
            'merchant_agent1_commission', 'merchant_agent2_id', 'merchant_agent2_rate', 'merchant_agent2_commission', 'merchant_agent3_id',
            'merchant_agent3_rate', 'merchant_agent3_commission', 'remark', 'bank_id', 'mid', 'currency_id', 'status', 'merchant_fee', 'callback_count',
            'user_agent1_id', 'user_agent1_rate', 'user_agent1_commission', 'user_agent2_id', 'user_agent2_rate', 'user_agent2_commission',
            'user_agent3_id', 'user_agent3_rate', 'user_agent3_commission', 'user_agent4_id', 'user_agent4_rate', 'user_agent4_commission',
            'user_agent5_id', 'user_agent5_rate', 'user_agent5_commission', 'user_rate', 'user_commission', 'callback_status', 'bank_name',
            'bank_code', 'holder_name', 'card_no', 'bank_province', 'bank_city', 'bank_branch', 'pay_certificate_1', 'pay_certificate_2',
            'pay_certificate_3', 'channel_cost', 'profit', 'utr', 'pay_status', 'child_count',
        ];
    }
}

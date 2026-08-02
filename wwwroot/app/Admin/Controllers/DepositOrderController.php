<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Admin;
use App\Models\AgentUser;
use Dcat\Admin\Layout\Row;
use App\Models\DepositOrder;
use App\Models\UserRelation;
use Dcat\Admin\Layout\Column;
use App\Models\AgentUserRelation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Admin\Actions\Grid\DepositeOrder\Logs;
use App\Admin\Extensions\Tools\AutoRefresh;
use App\Admin\Metrics\Admin\DepositOrder\Card1;
use App\Admin\Metrics\Admin\DepositOrder\Card2;
use App\Admin\Metrics\Admin\DepositOrder\Card3;
use App\Admin\Metrics\Admin\DepositOrder\Card4;
use App\Admin\Metrics\Admin\DepositOrder\Card5;
use App\Admin\Metrics\Admin\DepositOrder\Card6;
use App\Admin\Metrics\Admin\DepositOrder\Card7;
use App\Services\Cache\User\GetUserListService;
use App\Services\Common\DataFormatBnameService;
use App\Services\Cache\User\GetUserDetailService;
use App\Admin\Actions\Grid\DepositeOrder\OrderFail;
use App\Admin\Actions\Grid\DepositeOrder\ExportData;
use App\Services\Cache\User\GetUserAgentListService;
use App\Admin\Actions\Grid\DepositeOrder\FreezeOrder;
use App\Services\Cache\Channel\GetChannelListService;
use App\Admin\Actions\Grid\DepositeOrder\OrderSuccess;
use App\Admin\Actions\Grid\DepositeOrder\OrderTimeout;
use App\Admin\Actions\Grid\DepositeOrder\BatchCallback;
use App\Admin\Actions\Grid\DepositeOrder\CallbackRecords;
use App\Services\Cache\UserBank\GetUserBankDetailService;
use App\Admin\Actions\Grid\DepositeOrder\MerchantCallback;
use App\Admin\Actions\Grid\DepositeOrder\QueryOrderStatus;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;

class DepositOrderController extends CommonController
{
    protected $disableEdit = true;

    protected $disableCreate = true;

    protected function grid(): Grid
    {
        $autoRefreshToolbar = new AutoRefresh('deposit-orders');
        $adminUser = Admin::user();
        $canBatchCallback = $adminUser->can('deposit-order-batch-callback');
        $canManualSuccess = $adminUser->can('deposit-order-manual-success');
        $canManualFail = $adminUser->can('deposit-order-manual-fail');
        $canManualTimeout = $adminUser->can('deposit-order-manual-timeout');
        $canFreeze = $adminUser->can('deposit-order-freeze');
        $canCallback = $adminUser->can('deposit-order-callback');
        $canQueryStatus = $adminUser->can('deposit-order-query-status');
        $paymentMap = collect(config('payment'))->keyBy('id');
        $currencyMap = collect(config('default.currency'))->keyBy('id');
        $depositStatus = config('default.deposite_status', []);
        $depositPayStatus = config('default.deposite_pay_status', []);
        $formatService = App::make(DataFormatBnameService::class);
        $userDetailService = App::make(GetUserDetailService::class);
        $channelOptions = collect(App::make(GetChannelListService::class)->excute())->pluck('bname', 'id');
        $paymentOptions = collect($formatService->excute(config('payment')))->pluck('bname', 'id');
        $currencyOptions = collect($formatService->excute(config('default.currency')))->pluck('bname', 'id');
        $userAgentOptions = bob_build_select_options(App::make(GetUserAgentListService::class)->excute());
        $merchantAgentOptions = bob_build_select_options(App::make(GetMerchantAgentListService::class)->excute());
        $getUserDetail = function ($id) use ($userDetailService): array {
            static $cache = [];
            $id = (int) $id;
            if ($id <= 0) {
                return [];
            }
            if (!array_key_exists($id, $cache)) {
                $cache[$id] = $userDetailService->excute($id) ?: [];
            }

            return $cache[$id];
        };

        return Grid::make(DepositOrder::with(['admin', 'merchant_info' => function ($q) {
            $q->select(['merchant_user_id', 'name', 'coder', 'currency_id'])->withTrashed();
        }, 'channel' => function ($q) {
            $q->select(['id', 'name', 'deposit_order_query', 'classname']);
        }, 'user' => function ($q) {
            $q->select(['id', 'name']);
        }])->select(["id", "channel_id", 'channel_ordernumber', 'pay_certificate', 'user_id', "payment_id", "collection_name", "account_type", "collection_qrcode", "collection_bank_name", "collection_bank_branch", "collection_card_no", "order_no", "ordernumber", "pay_status", "hand_success", "hand_admin_id", "callback_time", "amount", "pay_amount", "actual_amount", "success_time", "pay_name", "created_at", "merchant_rate", "merchant_extra_fee", "merchant_agent1_id", "merchant_agent1_rate", "merchant_agent1_commission", "merchant_agent2_id", "merchant_agent2_rate", "merchant_agent2_commission", "merchant_agent3_id", "merchant_agent3_rate", "merchant_agent3_commission", "remark", "bank_id", "mid", "currency_id", "status", "merchant_fee", "callback_count", "user_agent1_id", "user_agent1_rate", "user_agent1_commission", "user_agent2_id", "user_agent2_rate", "user_agent2_commission", "user_agent3_id", "user_agent3_rate", "user_agent3_commission", "user_agent4_id", "user_agent4_rate", "user_agent4_commission", "user_agent5_id", "user_agent5_rate", "user_agent5_commission", "user_bank_id", "user_rate", "user_commission", "callback_status", "channel_cost", "profit", 'uid', 'usdt_rate', 'utr', 'time', 'freeze_amount']), function (Grid $grid) use ($paymentMap, $currencyMap, $depositStatus, $depositPayStatus, $channelOptions, $paymentOptions, $currencyOptions, $userAgentOptions, $merchantAgentOptions, $getUserDetail, $autoRefreshToolbar, $canBatchCallback, $canManualSuccess, $canManualFail, $canManualTimeout, $canFreeze, $canCallback, $canQueryStatus) {
            $created_at = request('created_at');
            $begin_date = $created_at['start'] ?? date('Y-m-d') . " 00:00:00";
            $end_date = $created_at['end'] ?? date('Y-m-d') . " 23:59:59";
            $grid->column('id')->sortable()->append(function () {
                if ($this->time) {
                    return '<span class="label" style="background:#ef5228">旧</span>';
                }
            });
            $grid->column('channel_info', '渠道信息')->display(function () use ($getUserDetail) {
                if ($this->channel_id > 0) {
                    if ($this->user_id > 0) {
                        $user = $getUserDetail($this->user_id);
                        if (!empty($user)) {
                            $data[] = ["金主", optional($user)->offsetGet('bname')];
                            $data[] = ["排单次数", optional($user)->offsetGet('round_times')];
                            $data[] = ["金主费率", (floatval($this->user_rate) * 100) . "%"];
                            $data[] = ["金主佣金", bob_unit_format($this->user_commission)];
                        }
                        if ($this->user_agent1_id > 0) {
                            $agent1 = $getUserDetail($this->user_agent1_id);
                            $data[] = ["金主一级代理", optional($agent1)->offsetGet('bname')];
                            $data[] = ["金主一级代理费率", (floatval($this->user_agent1_rate) * 100) . "%"];
                            $data[] = ["金主一级代理佣金", bob_unit_format($this->user_agent1_commission)];
                        }
                        if ($this->user_agent2_id > 0) {
                            $agent2 = $getUserDetail($this->user_agent2_id);
                            $data[] = ["金主二级代理", optional($agent2)->offsetGet('bname')];
                            $data[] = ["金主二级代理费率", (floatval($this->user_agent2_rate) * 100) . "%"];
                            $data[] = ["金主二级代理佣金", bob_unit_format($this->user_agent2_commission)];
                        }
                        if ($this->user_agent3_id > 0) {
                            $agent3 = $getUserDetail($this->user_agent3_id);
                            $data[] = ["金主三级代理", optional($agent3)->offsetGet('bname')];
                            $data[] = ["金主三级代理费率", (floatval($this->user_agent3_rate) * 100) . "%"];
                            $data[] = ["金主三级代理佣金", bob_unit_format($this->user_agent3_commission)];
                        }
                        if ($this->user_agent4_id > 0) {
                            $agent4 = $getUserDetail($this->user_agent4_id);
                            $data[] = ["金主四级代理", optional($agent4)->offsetGet('bname')];
                            $data[] = ["金主四级代理费率", (floatval($this->user_agent4_rate) * 100) . "%"];
                            $data[] = ["金主四级代理佣金", bob_unit_format($this->user_agent4_commission)];
                        }
                        if ($this->user_agent5_id > 0) {
                            $agent5 = $getUserDetail($this->user_agent5_id);
                            $data[] = ["金主五级代理", optional($agent5)->offsetGet('bname')];
                            $data[] = ["金主五级代理费率", (floatval($this->user_agent5_rate) * 100) . "%"];
                            $data[] = ["金主五级代理佣金", bob_unit_format($this->user_agent5_commission)];
                        }
                        return bob_show_table_info($data);
                    }
                    if ($this->channel_id > 0) {
                        $data[] = ["渠道名称", "【#" . $this->channel_id . "】" . $this->channel->name];
                        if ($this->channel_ordernumber) $data[] = ["渠道单号", $this->channel_ordernumber];
                        return bob_show_table_info($data);
                    }
                }
                return '-';
            });
            $grid->column('pay_info', '收款信息')->display(function () use ($paymentMap) {
                if ($this->payment_id > 0) {
                    $payment = $paymentMap->get($this->payment_id);
                    $data[] = ["通道编码", $payment['name'] ?? ''];
                    if ($this->channel_id > 0 && $this->user_id == 0 && File::exists(base_path("vendor/richard/payment/src/Channel/" . $this->channel->classname . ".php"))) {
                        $classname = 'Richard\\Payment\\Channel\\' . $this->channel->classname;
                        $pay = new $classname();
                        $tongdao = $pay->setChannelCoder($this->payment_id);
                        if ($tongdao != '') $data[] = ["第三方通道", $tongdao];
                    }
                    if ($this->collection_name) $data[] = ["收款人名称", $this->collection_name . "【#" . $this->user_bank_id . "】"];
                    if ($this->account_type == 3 || $this->account_type == 5) {
                        $data[] = ["二维码", '<img src="' . $this->qrcode_url_format . '" width="100"/>'];
                        return bob_show_table_info($data);
                    }
                    if ($this->collection_bank_name) $data[] = ["银行名称", $this->collection_bank_name];
                    if ($this->collection_bank_branch) $data[] = ["银行支行", $this->collection_bank_branch];
                    if ($this->collection_card_no) $data[] = ["收款账号", $this->collection_card_no];
                    if ($this->utr) $data[] = ["UTR", $this->utr];
                    if ($this->uid) $data[] = ["会员ID", $this->uid];
                    return bob_show_table_info($data, [], ['tr-1', 'tr-2', 'tr-3', 'tr-4'], 4);
                }
                return;
            });
            $grid->column('ordernumer_info', '商户订单号/平台订单号')->display(function () {
                $data[] = ["商户单号", $this->order_no];
                $data[] = ["平台单号", $this->ordernumber];
                return bob_show_table_info($data);
            });
            $grid->column('merchant_info.bname', '商户');
            $grid->column('currency_id', '币种')->display(function () use ($currencyMap) {
                return optional($currencyMap->get($this->currency_id))->offsetGet('name');
            });
            $grid->column('status', '订单状态')->display(function () use ($depositStatus, $depositPayStatus) {
                $data[] = ["订单状态", bob_show_label($depositStatus[$this->status] ?? '', $this->status, 2) . ($this->hand_success == 1 ? '【补】' : '')];
                if ($this->pay_status > 1) {
                    if ($this->pay_status == 2) {
                        $data[] = ["支付状态", '<span class="label bg-success margin-r-5">' . ($depositPayStatus[$this->pay_status] ?? '') . '</span>'];
                        if (!empty($this->pay_certificate)) {
                            $data[] = ['付款凭证', '<a href="' . Storage::disk('admin')->url($this->pay_certificate) . '" target="_blank">查看</a>'];
                        }
                    }
                    if ($this->pay_status == 3) {
                        $data[] = ["支付状态", '<span class="label bg-red margin-r-5">' . ($depositPayStatus[$this->pay_status] ?? '') . '</span>'];
                    }
                }
                if ($this->hand_success == 1) {
                    $data[] = ['操作人', $this->admin ? $this->admin->name : ''];
                }
                return bob_show_table_info($data);
            });
            $grid->column('callback_info', "推送信息")->display(function () {
                if ($this->status == 5 || $this->status == 6) {
                    $data[] = ["推送次数", $this->callback_count];
                    if ($this->callback_time > 0) {
                        $data[] = ["推送时间", date('Y-m-d H:i:s', $this->callback_time)];
                    }
                    if ($this->callback_status == 1) {
                        $data[] = ["推送状态", '<span class="label bg-success margin-r-5">推送成功</span>'];
                    }
                    if ($this->callback_status == 2) {
                        $data[] = ["推送状态", '<span class="label bg-red margin-r-5">推送失败</span>'];
                    }
                    return bob_show_table_info($data);
                }
                return;
            });
            $grid->column('amount_info', "金额信息")->display(function () {
                $data[] = ["提交金额", bob_unit_format($this->amount)];
                $data[] = ["订单金额", bob_unit_format($this->pay_amount)];
                $data[] = ["实付金额", bob_unit_format($this->actual_amount)];
                if ($this->freeze_amount > 0) {
                    $data[] = ["冻结金额", bob_unit_format($this->freeze_amount)];
                }
                if ($this->usdt_rate > 0 && $this->actual_amount > 0) {
                    $amount = $this->actual_amount - $this->merchant_fee - $this->merchant_extra_fee;
                    $data[] = ["入账金额", floatval($amount)];
                    $data[] = ["USDT实时费率", floatval($this->usdt_rate)];
                    $data[] = ["USDT入账金额", floatval(bcdiv($amount, $this->usdt_rate, 2))];
                }
                return bob_show_table_info($data);
            });

            $grid->column('success_time', "成功时间")->display(function ($value) {
                if ($this->status == 5) {
                    return date('Y-m-d H:i:s', $value);
                }
                return;
            });
            $grid->column('pay_name', "付款人名称");
            $grid->column('created_at', "订单时间");
            $grid->column('merchant_fee', "手续费")->display(function ($value) {
                $data[] = ['商户费率', (floatval($this->merchant_rate) * 100) . "%"];
                $data[] = ['商户手续费', bob_unit_format($value)];
                $data[] = ['商户额外手续费', bob_unit_format($this->merchant_extra_fee)];
                if ($this->merchant_agent1_id > 0) {
                    $agent_user = AgentUser::select(['id', 'name'])->find($this->merchant_agent1_id);
                    if ($agent_user) {
                        $data[] = ['商户一级代理', optional($agent_user)->offsetGet('name')];
                        $data[] = ['商户一级代理费率', (floatval($this->merchant_agent1_rate) * 100) . "%"];
                        $data[] = ['商户一级代理佣金', bob_unit_format($this->merchant_agent1_commission)];

                        $agent_user_parent = $agent_user->getAncestors();
                        if (!empty($agent_user_parent)) {
                            if (count($agent_user_parent) >= 1 && $this->merchant_agent2_id > 0) {
                                $data[] = ['商户二级代理', optional($agent_user_parent->firstWhere('id', $this->merchant_agent2_id))->offsetGet('name')];
                                $data[] = ['商户二级代理费率', (floatval($this->merchant_agent2_rate) * 100) . "%"];
                                $data[] = ['商户二级代理佣金', bob_unit_format($this->merchant_agent2_commission)];
                            }
                            if (count($agent_user_parent) >= 2 && $this->merchant_agent3_id > 0) {
                                $data[] = ['商户三级代理', optional($agent_user_parent->firstWhere('id', $this->merchant_agent3_id))->offsetGet('name')];
                                $data[] = ['商户三级代理费率', (floatval($this->merchant_agent3_rate) * 100) . "%"];
                                $data[] = ['商户三级代理佣金', bob_unit_format($this->merchant_agent3_commission)];
                            }
                        }
                    }
                }
                if ($this->status == 5) {
                    $data[] = ['渠道成本', bob_unit_format($this->channel_cost)];
                    $data[] = ['系统利润', bob_unit_format($this->profit)];
                }
                return bob_show_table_info($data, [], [], 2);
            });
            $grid->column('remark', "备注")->limit(30);
            $grid->disableCreateButton();
            $grid->disableDeleteButton();
            $grid->disableEditButton();
            $grid->showColumnSelector();

            $grid->tools(function (Grid\Tools $tools) use ($canBatchCallback, $autoRefreshToolbar) {
                $tools->append($autoRefreshToolbar);
                if ($canBatchCallback) {
                    $tools->append(new BatchCallback());
                }
                $tools->append(new ExportData());

            });

            $grid->actions(function ($actions) use ($canManualSuccess, $canManualFail, $canManualTimeout, $canFreeze, $canCallback, $canQueryStatus) {
                $actions->append(new Logs());
                $actions->append(new CallbackRecords());

                if ($actions->row['status'] == 1 || $actions->row['status'] == 3 || $actions->row['status'] == 4 || $actions->row['status'] == 7) {
                    if ($canManualSuccess) {
                        $actions->append(new OrderSuccess());
                    }
                    if ($canManualFail) {
                        $actions->append(new OrderFail());
                    }
                    if ($canManualTimeout) {
                        $actions->append(new OrderTimeout());
                    }
                }
                if ($actions->row['status'] == 5) {
                    if ($canFreeze) {
                        $actions->append(new FreezeOrder());
                    }
                    if ($canCallback) {
                        $actions->append(new MerchantCallback());
                    }
                }
                if ($actions->row['status'] == 6 && $canCallback) {
                    $actions->append(new MerchantCallback());
                }
                if ($actions->row['status'] == 3 || $actions->row['status'] == 4) {
                    if ($canQueryStatus && $actions->row['channel'] && $actions->row['channel']['deposit_order_query'] == 1) {
                        $actions->append(new QueryOrderStatus());
                    }
                }
            });

            $grid->header(function () use ($begin_date, $end_date) {
                $row = new Row();
                $row->column(12, function (Column $column) use ($row, $begin_date, $end_date) {
                    $row->column(4, new Card1(request()->all(), $begin_date, $end_date));
                    $row->column(4, new Card6(request()->all(), $begin_date, $end_date));
                    $row->column(4, new Card3(request()->all(), $begin_date, $end_date));
                    $row->column(4, new Card4(request()->all(), $begin_date, $end_date));
                    $row->column(4, new Card5(request()->all(), $begin_date, $end_date));
                    $row->column(4, new Card2(request()->all(), $begin_date, $end_date));
                    if (config('app.name') == 'haoyunlai') {
                        $row->column(4, new Card7(request()->all(), $begin_date, $end_date));
                    }
                });
                return $row;
            });

            $grid->filter(function (Grid\Filter $filter) use ($begin_date, $end_date, $channelOptions, $paymentOptions, $currencyOptions, $userAgentOptions, $merchantAgentOptions, $getUserDetail) {
                if (request('created_at') === null) {
                    request()->merge(['created_at' => ['start' => $begin_date, 'end' => $end_date]]);
                }
                $filter->expand();
                $filter->panel();
                $filter->equal('id', '订单编号')->width(3);
                if (config("app.name") == "sgpay") {
                    $filter->equal('collection_card_no', '金主收款卡账号')->width(3);
                }
                $filter->equal('user_bank_id', "金主收款卡")->select(function ($user_bank_id) {
                    if ($user_bank_id) {
                        $result = App::make(GetUserBankDetailService::class)->excute($user_bank_id);
                        if (!empty($result)) {
                            return [$result['id'] => $result['bname']];
                        }
                    }
                    return [];
                })->ajax("/ajax/getUserBankList", 'id', 'bname')->width(3);
                $filter->equal('mid', "商户")->select(function ($mid) {
                    if ($mid) {
                        $result = App::make(CacheMerchantBaseInfoService::class)->excute($mid);
                        if (!empty($result)) {
                            return [$result['merchant_user_id'] => $result['bname']];
                        }
                    }
                    return [];
                })->ajax("/ajax/getMerchantList", "merchant_user_id", "bname")->width(3);
                $filter->equal('channel_id', "支付渠道")->select($channelOptions)->width(3);
                $filter->equal("pay_name", "付款人名称")->width(3);
                $filter->equal('amount', "提交金额")->width(3);
                $filter->equal('pay_amount', "订单金额")->width(3);
                $filter->equal('actual_amount', "实付金额")->width(3);
                $filter->equal("ordernumber", "平台单号")->width(3);
                $filter->equal("order_no", "商户单号")->width(3);
                $filter->equal("channel_ordernumber", "渠道单号")->width(3);
                $filter->equal('status', "订单状态")->select(config('default.deposite_status'))->width(3);
                $filter->equal('hand_success', "人工补单")->select([0 => '否', 1 => "是"])->width(3);
                if (config('app.name') == "sgpay") {
                    $filter->equal('user_id', "金主")->select(collect(App::make(GetUserListService::class)->excute())->pluck('bname', 'id'))->width(3);
                } else {
                    $filter->equal('user_id', "金主")->select(function ($user_id) use ($getUserDetail) {
                        if ($user_id) {
                            $result = $getUserDetail($user_id);
                            if (!empty($result)) {
                                return [$result['id'] => $result['bname']];
                            }
                        }
                        return [];
                    })->ajax("ajax/getUserList", "id", "bname")->width(3);
                }


                $filter->equal('payment_id', "支付通道")->select($paymentOptions)->width(3);
                $filter->between('created_at', "创建时间")->datetime()->width(3)->default(['start' => $begin_date, 'end' => $end_date]);
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
                $filter->where('user_agent_id', function ($query) {
                    $query->whereIn('user_id', UserRelation::where('parent_id', $this->input)->pluck('child_id'));
                }, '金主代理')->select($userAgentOptions)->width(3);
                $filter->where('merchant_agent_id', function ($query) {
                    $query->whereIn('merchant_agent1_id', AgentUserRelation::where('parent_id', $this->input)->pluck('child_id'));
                }, '商户代理')->select($merchantAgentOptions)->width(3);
                $filter->equal('currency_id', "请选择币种")->select($currencyOptions)->width(3);
                $filter->equal('callback_status', "回调状态")->select([0 => "未回调", 1 => "回调成功", 2 => "回调失败"])->width(3);
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

}

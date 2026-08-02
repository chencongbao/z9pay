<?php

namespace App\Services\DepositOrder;

use App\Models\Channel;
use Illuminate\Support\Arr;
use App\Models\DepositOrder;
use App\Traits\ServiceTraits;
use App\Models\ChannelAccount;
use App\Models\MerchantChannel;
use Illuminate\Support\Facades\App;
use App\Services\Channel\CheckChannelCurrencyService;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;
use App\Services\DepositOrderLog\CreateDepositOrderLogService;
use App\Services\Cache\DepositOrder\DepositOrderCacheChannelAutoPriorityService;

class GetPaymentInfoService
{
    use ServiceTraits;

    private $filename = "GetPaymentInfoService";

    private $pre_name = "获取支付渠道，";

    protected $createDepositOrderLogService;

    public $error = "";

    function __construct($filename = "")
    {
        if (!empty($filename)) $this->filename = $filename;
        $this->createDepositOrderLogService = App::make(CreateDepositOrderLogService::class);
    }

    public function excute($order = '')
    {
        if ($order) {
            //匹配手动配置的渠道
            $merchant_channel_result = MerchantChannel::where('merchant_user_id', $order->mid)->where('payment_id', $order->payment_id)->where('status', 1)->whereHas('channel', function ($q) {
                $q->where('status', 1);
            })->with(['channel'])->orderBy('priority', 'asc')->orderBy('id', 'desc')->get();
            if ($merchant_channel_result->isEmpty()) {
                $this->createDepositOrderLogService->excute($order->id, "未查询到手动配置的渠道", [], "debug");
            }
            if (!$merchant_channel_result->isEmpty()) {
                $all_chanel = $merchant_channel_result->map(function ($item) {
                    return $item->channel->name;
                });
                $this->createDepositOrderLogService->excute($order->id, "手动配置渠道", $all_chanel->toArray(), "debug");
                foreach ($merchant_channel_result as $merchant_channel_item) {

                    $channel_account = ChannelAccount::where('channel_id', $merchant_channel_item->channel_id)->orderBy('id', 'desc')->where('status', 1)->first();
                    if (!$channel_account) {
                        $this->createDepositOrderLogService->excute($order->id, "手动配置渠道:" . $merchant_channel_item->channel->name . ",渠道账号已关闭", [], "error");
                        continue;
                    }

                    if (!App::make(CheckChannelCurrencyService::class)->excute($order->currency_id, $merchant_channel_item->channel->currency)) {
                        $this->createDepositOrderLogService->excute($order->id, "手动配置渠道:" . $merchant_channel_item->channel->name . ",代收币种不支持", ['代收币种' => optional(collect(config('default.currency'))->firstWhere('id', $order->currency_id))->offsetGet('name'), "支持币种" => $this->parseCurrencyName($merchant_channel_item->channel->currency)], "error");
                        continue;
                    }

                    if ($merchant_channel_item->pay_min_amount > 0 && $order->amount < $merchant_channel_item->pay_min_amount) {
                        $this->createDepositOrderLogService->excute($order->id, "手动配置渠道:" . $merchant_channel_item->channel->name . ",付款金额小于渠道单笔下限", ['充值金额' => $order->amount, "渠道单笔下限" => $merchant_channel_item->pay_min_amount], "error");
                        continue;
                    }
                    if ($merchant_channel_item->pay_max_amount > 0 && $order->amount > $merchant_channel_item->pay_max_amount) {
                        $this->createDepositOrderLogService->excute($order->id, "手动配置渠道:" . $merchant_channel_item->channel->name . ",付款金额大于渠道单笔上限", ['充值金额' => $order->amount, "渠道单笔上限" => $merchant_channel_item->pay_max_amount], "error");
                        continue;
                    }
                    if ($order->data_type == 'json' && $merchant_channel_item->channel->is_json_return == 0) {
                        $this->createDepositOrderLogService->excute($order->id, "手动配置渠道:" . $merchant_channel_item->channel->name . ",当前渠道不支持JSON返回", ['充值金额' => $order->amount, "渠道单笔上限" => $merchant_channel_item->pay_max_amount], "error");
                        continue;
                    }
                    $this->data[] = [
                        'payment_id' => $order->payment_id,
                        'channel_id' => $merchant_channel_item->channel_id,
                        'name' => $merchant_channel_item->channel->name,
                        'classname' => $merchant_channel_item->channel->classname,
                        'is_real_name' => $merchant_channel_item->channel->is_real_name,
                        'auto_priority' => App::make(DepositOrderCacheChannelAutoPriorityService::class)->excute($order->mid . "_" . $merchant_channel_item->channel_id . "_" . $order->payment_id),
                        'deposit_order_id' => $order->id,
                        'data_type' => $order->data_type,
                        'pay_name' => $order->pay_name,
                        'ordernumber' => $order->ordernumber,
                        'status' => $merchant_channel_item->channel_id == 1 ? 1 : 3,
                        'mid' => $order->mid,
                        'amount' => $order->amount,
                        'merchant_extra_fee' => $merchant_channel_item->deposit_fee,
                        'float_status' => $merchant_channel_item->float_status,
                        'settlement_mode' => $merchant_channel_item->settlement_mode,
                        'settlement_time' => $merchant_channel_item->settlement_time,
                    ];
                }
            }
            return $this->selectChannel($this->data, $order->id, optional($order)->offsetGet('pay_name'),$order->mid);
        }
        return;
    }

    private function parseCurrencyName($currency = "")
    {
        if (empty($currency)) return collect(config('default.currency'))->pluck('name');
        return collect(explode(",", $currency))->map(function ($item) {
            return optional(collect(config('default.currency'))->firstWhere('id', $item))->offsetGet('name');
        })->all();
    }

    private function selectChannel($data = [], $deposit_order_id = 0, $pay_name = '',$mid = 0)
    {
        if (empty($data)) {
            $this->error = "未获取到符合条件的渠道";
            return;
        }
        $this->createDepositOrderLogService->excute($deposit_order_id, "最终符合条件的手动配置的渠道", $data, "DEBUG");
        $channel_mode = intval(bob_admin_setting("other_deposit_channel_mode"));
        $merchant = App::make(CacheMerchantBaseInfoService::class)->excute($mid);
        if(!empty($merchant) && isset($merchant['deposit_channel_mode']) && $merchant['deposit_channel_mode'] > 0){
            $channel_mode = intval($merchant['deposit_channel_mode']);
        }
        $result = [];
        if ($channel_mode == 1) { //按优先级返回
            if(empty($pay_name)){
                return ['channel_info' => $data,'status'=>1];
            }
            foreach ($data as $k => $v) {
                $this->createDepositOrderLogService->excute($deposit_order_id, "按优先级成功匹配手动配置的渠道", ['付款人姓名' => $pay_name,'data'=>$v], "debug");
                App::make(DepositOrderPayAmountService::class)->applyByChannelData($v);
                if ($v['is_real_name'] == 1 && empty($pay_name)) {
                    $result = ['channel_id' => $v['channel_id'],'status'=>1,'merchant_extra_fee' => $v['merchant_extra_fee'],'settlement_mode'=>$v['settlement_mode'],'settlement_time'=>bob_settlement_time($v['settlement_mode'],$v['settlement_time'])];
                    break;
                }
                $classname = 'Richard\\Payment\\Channel\\' . $v['classname'];
                $pay = new $classname($this->filename);
                $row = $pay->deposit($v['deposit_order_id']);
                if (empty($row)) {
                    $this->error = $pay->error;
                    bob_send_channel_exception_notice(['error' => $pay->error, "ordernumber" => $v['ordernumber'], "title" => "通道调用异常报警", "channel_name" => $v['name'], "action" => "代收订单渠道调用错误"]);
                    unset($pay);
                    continue;
                }
                $result = Arr::collapse([$row,['merchant_extra_fee'=>$v['merchant_extra_fee'],'settlement_mode'=>$v['settlement_mode'],'settlement_time'=>bob_settlement_time($v['settlement_mode'],$v['settlement_time'])]]);
                break;
            }
            if (!empty($result)) {
                return Arr::collapse([['status' => 3],$result]);
            }
            if(empty($this->error))$this->error = "未获取到符合条件的渠道";
            return null;
        }
        if ($channel_mode == 2) { //随机返回
            $result = Arr::random($data);
            $this->createDepositOrderLogService->excute($result['deposit_order_id'], "随机成功匹配手动配置的渠道", $result['name'], "debug");
        }
        if ($channel_mode == 3) { //按平均数返回
            usort($data, function ($a, $b) {
                return $a['auto_priority'] - $b['auto_priority'];
            });
            $result = $data[0];
            $this->updatePriority($data, $result);
            $this->createDepositOrderLogService->excute($result['deposit_order_id'], "按平均成功匹配手动配置的渠道", $result['name'], "debug");
        }
        if (empty($result)) {
            app(\App\Services\SystemNotice\SystemNoticeService::class)->warning("system_manual_notice", ['text' => '代收渠道匹配模式位置', 'channel_mode' => $channel_mode]);
            return [];
        }
        App::make(DepositOrderPayAmountService::class)->applyByChannelData($result);
        $this->updateChannel($result['deposit_order_id'], $result['channel_id']);
        $channel = Channel::where('id', $result['channel_id'])->first(['id', 'is_json_return', 'is_real_name','code','name']);
        $this->createDepositOrderLogService->excute($result['deposit_order_id'], "渠道信息", $channel->toArray(), "DEBUG");
        if ($channel->is_real_name == 1) {
            if ($result['data_type'] == 'json') {
                if ($result['pay_name']) {
                    $classname = 'Richard\\Payment\\Channel\\' . $result['classname'];
                    $pay = new $classname($this->filename);
                    $row = $pay->deposit($result['deposit_order_id']);
                    if (!empty($row)) {
                        return Arr::collapse([$row, ['status' => 3,'merchant_extra_fee'=>$result['merchant_extra_fee'],'settlement_mode'=>$result['settlement_mode'],'settlement_time'=>bob_settlement_time($result['settlement_mode'],$result['settlement_time'])]]);
                    }
                    $this->error = $pay->error;
                    //通知渠道群
                    bob_send_channel_exception_notice(['error' => $pay->error, "ordernumber" => $result['ordernumber'], "title" => "通道调用异常报警", "channel_name" => $result['name'], "action" => "代收订单渠道调用错误"]);
                    //系统后台通知
                    bob_send_system_deposit_notice(['error_text' => '代收订单渠道返回错误，渠道名称：' . $result['name'] . '，错误内容：' . $pay->error . '，订单号：' . $result['ordernumber'], 'voice_id' => 'deposit_10', 'id' => 10]);
                    return [];
                } else {
                    $this->createDepositOrderLogService->excute($result['deposit_order_id'], "渠道要求实名认证，商家需要返回json，无法下单", ['渠道名称' => $result['name']], "error");
                    return [];
                }
            } else {
                if ($result['pay_name']) {
                    $classname = 'Richard\\Payment\\Channel\\' . $result['classname'];
                    $pay = new $classname($this->filename);
                    $row = $pay->deposit($result['deposit_order_id']);
                    if (!empty($row)) {
                        return Arr::collapse([$row, ['status' => 3,'merchant_extra_fee'=>$result['merchant_extra_fee'],'settlement_mode'=>$result['settlement_mode'],'settlement_time'=>bob_settlement_time($result['settlement_mode'],$result['settlement_time'])]]);
                    }
                    //系统后台通知
                    bob_send_system_deposit_notice(['error_text' => '代收订单渠道返回错误，渠道名称：' . $result['name'] . '，错误内容：' . $pay->error . '，订单号：' . $result['ordernumber'], 'voice_id' => 'deposit_10', 'id' => 10]);
                    //通知渠道群
                    bob_send_channel_exception_notice(['error' => $pay->error, "ordernumber" => $result['ordernumber'], "title" => "通道调用异常报警", "channel_name" => $result['name'], "action" => "代收订单渠道调用错误"]);
                    $this->error = $pay->error;
                    return [];
                } else {
                    return ['channel_id' => $channel->id,'merchant_extra_fee'=>$result['merchant_extra_fee'],'settlement_mode'=>$result['settlement_mode'],'settlement_time'=>bob_settlement_time($result['settlement_mode'],$result['settlement_time'])];
                }
            }
        } else {
            $classname = 'Richard\\Payment\\Channel\\' . $result['classname'];
            $pay = new $classname($this->filename);
            $row = $pay->deposit($result['deposit_order_id']);
            if (!empty($row)) {
                return Arr::collapse([$row, ['status' => 3,'merchant_extra_fee'=>$result['merchant_extra_fee'],'settlement_mode'=>$result['settlement_mode'],'settlement_time'=>bob_settlement_time($result['settlement_mode'],$result['settlement_time'])]]);
            }
            //系统后台通知
            bob_send_system_deposit_notice(['error_text' => '代收订单渠道返回错误，渠道名称：' . $result['name'] . '，错误内容：' . $pay->error . '，订单号：' . $result['ordernumber'], 'voice_id' => 'deposit_10', 'id' => 10]);
            //通知渠道群
            bob_send_channel_exception_notice(['error' => $pay->error, "ordernumber" => $result['ordernumber'], "title" => "通道调用异常报警", "channel_name" => $result['name'], "action" => "代收订单渠道调用错误"]);
            $this->error = $pay->error;
            return [];
        }
    }

    private function updateChannel($deposit_order_id, $channel_id)
    {
        DepositOrder::where('id', $deposit_order_id)->update(['channel_id' => $channel_id]);
    }

    private function updatePriority($data = [], $result = [])
    {
        $last_channel_id = optional(collect($data)->last())->offsetGet('channel_id');
        if ($last_channel_id) {
            $auto_priority = App::make(DepositOrderCacheChannelAutoPriorityService::class)->excute($result['mid'] . "_" . $last_channel_id . "_" . $result['payment_id']);
            App::make(DepositOrderCacheChannelAutoPriorityService::class)->set($auto_priority + 1, $result['mid'] . "_" . $result['channel_id'] . "_" . $result['payment_id']);
        }
    }
}

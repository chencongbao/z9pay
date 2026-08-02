<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Models\TransferOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Services\Const\LogConstService;
use Illuminate\Support\Facades\Storage;
use App\Services\Common\SystemLogService;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\TransferOrderResource;
use App\Http\Resources\TransferOrderCollection;
use App\Services\Cache\CacheConstPrefixService;
use App\Http\Requests\Api\V2\UploadImageRequest;
use App\Services\TransferOrder\TransferOrderSuccessService;
use App\Http\Requests\Api\V2\TransferOrdersubmitOrderRequest;
use App\Services\SettlementOrder\SettlementOrderSuccessService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;
use App\Services\Report\OrderStatusReportRepairService;

class TransferOrderController extends ApiController
{

    public $field = ['id','actual_amount','status','type','user_id','amount','holder_name','card_no','bank_name','ordernumber','updated_at','bank_code','created_at','user_commission'];

    public function index(Request $request)
    {
        $model = new TransferOrder();
        $model = $model->where('user_id', $request->user()->id)->orderBy('id','desc');
        if($request->input("ordernumber")){
            $model = $model->where('ordernumber', $request->input("ordernumber"));
        }
        if($request->input("status")){
            $model = $model->where('status', $request->input("status"));
        }
        if($request->input("time") == 1){
            $model = $model->where('created_at','>=', date('Y-m-d')." 00:00:00")->where('created_at','<=', date('Y-m-d')." 23:59:59");
        }
        $result = $model->select($this->field)->orderBy('id','desc')->with(['user'=>function ($query) {
            $query->select('id','name','username');
        }])->paginate($this->pageSize);
        $this->data['lists'] = TransferOrderCollection::make($result);
        return $this->success('', $this->data);
    }


    public function initOrder(Request $request)
    {
        $order = TransferOrder::where('status',2)->where('user_id',$request->user()->id)->where('pay_status',1)->first($this->field);
        if($order){
            $this->data['order'] = TransferOrderResource::make($order);
        }
        return $this->success('',$this->data);
    }

    public function searchOrder(Request $request)
    {
        $this->data['lists'] = [];
        $user = User::where('id',$request->user()->id)->first(['id','is_agent','status','acquisition_status','pay_group_merchant_user_ids']);
        if($user){
            if($user->is_agent == 1 || $user->status != 1 || $user->acquisition_status != 1){
                $this->data['lists'] = [];
            }else{
                if(!empty($user->pay_group_merchant_user_ids)){
                    $order = TransferOrder::whereIn('mid',explode(",",$user->pay_group_merchant_user_ids))->where('user_id',0)->where('currency_id',1)->where('channel_id',1)->where('status',2)->where('pay_status',0)->get($this->field);
                    $this->data['lists'] = TransferOrderCollection::make($order);
                }else{
                    $order = TransferOrder::where('status',2)->where('user_id',0)->where('currency_id',1)->where('channel_id',1)->where('pay_status',0)->get($this->field);
                    $this->data['lists'] = TransferOrderCollection::make($order);
                }
            }
        }
        return $this->success('',$this->data);
    }

    public function receviceOrder(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = User::where('id',$request->user()->id)->where('is_agent',0)->first(['id','pay_limit_max','pay_limit_min','name','username','status','acquisition_status','pay_group_merchant_user_ids']);
            if($user){
                if($user->status != 1) throw new \Exception("用户已禁用");
                if($user->acquisition_status != 1) throw new \Exception("接单已关闭");
                $orderQuery = TransferOrder::lockForUpdate()
                    ->where('id',$request->input('id'))
                    ->where('user_id',0)
                    ->where('currency_id',1)
                    ->where('channel_id',1)
                    ->where('status',2)
                    ->where('pay_status',0);
                if(!empty($user->pay_group_merchant_user_ids)){
                    $orderQuery->whereIn('mid', array_filter(explode(',', $user->pay_group_merchant_user_ids)));
                }
                $order = $orderQuery->first(CacheConstPrefixService::CACHE_TRANSFER_FILED);
                if($order){
                    if($user->pay_limit_max > 0 && floatval($order->amount) > $user->pay_limit_max){
                        throw new \Exception('单笔代付限额:'.$user->pay_limit_min."-".$user->pay_limit_max);
                    }
                    if($user->pay_limit_min > 0 && floatval($order->amount) < $user->pay_limit_min){
                        throw new \Exception('单笔代付限额:'.$user->pay_limit_min."-".$user->pay_limit_max);
                    }
                    $order->user_id = $request->user()->id;
                    $order->pay_status = 1;
                    $order->save();
                    $order->refresh();
                    $createTransferOrderLogService = App::makeWith(CreateTransferOrderLogService::class,['filename'=>LogConstService::TRANSFER_ORDER_LOG_PREFIX.$order->id]);
                    $createTransferOrderLogService->excute($order->id,"金主接单","金主：".$user->bname);

                    app(SystemLogService::class)->logAction(
                        actionKey: 'api.v2.users.transfer-orders.receive-order',
                        text: '接单 代付订单',
                        subject: $order,
                        properties: [
                            'user_id' => $request->user()->id,
                            'order_id' => $order->id,
                            'ordernumber' => $order->ordernumber,
                            'amount' => $order->amount,
                        ],
                        remark: sprintf('接单 代付订单 %s', $order->ordernumber),
                        logType: 'operation',
                        actionMethod: 'PUT',
                        appType: 'user',
                        user: $request->user()
                    );

                    DB::commit();
                    cache_transfer_info($order);
                    $this->data['order'] = TransferOrderResource::make($order);
                    return $this->success("",$this->data);
                }
                throw new \Exception("未申购到此订单");
            }
            throw new \Exception("用户不存在");
        }catch (\Exception $e){
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }

    public function cancelOrder(Request $request)
    {
        DB::beginTransaction();
        try {
            $order = TransferOrder::lockForUpdate()->where('id',$request->input('id'))->where('user_id',$request->user()->id)->where('status',2)->where('pay_status',1)->with('user')->first($this->field);
            if(!$order) throw new \Exception("订单已失效");

            if($order->type == 0) $order->status = 3;
            if($order->type == 1) $order->status = 6;
            $order->remark = "【".$request->user()->bname."】取消订单";
            $order->user_id = 0;
            $order->pay_status = 0;
            $order->save();
            $createTransferOrderLogService = App::makeWith(CreateTransferOrderLogService::class,['filename'=>LogConstService::TRANSFER_ORDER_LOG_PREFIX.$order->id]);
            $createTransferOrderLogService->excute($order->id,"金主取消订单","金主：".$order->user->bname);

                app(SystemLogService::class)->logAction(
                actionKey: 'api.v2.users.transfer-orders.cancel-order',
                text: '取消 代付订单',
                subject: $order,
                properties: [
                    'user_id' => $request->user()->id,
                    'order_id' => $order->id,
                    'ordernumber' => $order->ordernumber,
                    'amount' => $order->amount,
                    'status' => $order->status,
                ],
                remark: sprintf('取消 代付订单 %s', $order->ordernumber),
                logType: 'operation',
                actionMethod: 'PUT',
                appType: 'user',
                user: $request->user()
            );

            DB::commit();
            cache_transfer_info($order);
            App::make(OrderStatusReportRepairService::class)->forTransferOrder($order);
            return $this->success();
        }catch (\Exception $e){
            DB::rollBack();
            return $this->error($e->getMessage());
        }
    }

    public function submitOrder(TransferOrdersubmitOrderRequest $request)
    {
        DB::beginTransaction();
        try {
            $user = User::where('id',$request->user()->id)->where('is_agent',0)->first(['id','pid','transfer_user_rate','user_rate','transfer_agent1_rate','agent1_rate','transfer_agent2_rate','agent2_rate','transfer_agent3_rate','agent3_rate','transfer_agent4_rate','agent4_rate','transfer_agent5_rate','agent5_rate','settlement_agent1_rate','settlement_agent2_rate','settlement_agent3_rate','settlement_agent4_rate','settlement_agent5_rate']);
            if($user){
                $order = TransferOrder::lockForUpdate()->where('id',$request->input('id'))->where('user_id',$request->user()->id)->where('status',2)->where('pay_status',1)->first(Arr::collapse([CacheConstPrefixService::CACHE_TRANSFER_FILED,['id','actual_amount','amount','status','hand_success','user_rate','type','user_agent1_rate','user_agent2_rate','user_agent3_rate','user_agent4_rate','user_agent5_rate','pay_certificate_1','pay_certificate_2','pay_certificate_3','user_agent1_id','user_agent2_id','user_agent3_id','user_agent4_id','user_agent5_id']]));
                if($order){
                    $order->user_rate = (floatval($user->transfer_user_rate) ?: floatval($user->user_rate)) / 100;
                    if($order->type == 0){
                        $order->user_agent1_rate = (floatval($user->transfer_agent1_rate) ?: $user->agent1_rate) / 100;
                        $order->user_agent2_rate = (floatval($user->transfer_agent2_rate) ?: floatval($user->agent2_rate)) / 100;
                        $order->user_agent3_rate = (floatval($user->transfer_agent3_rate) ?: floatval($user->agent3_rate)) / 100;
                        $order->user_agent4_rate = (floatval($user->transfer_agent4_rate) ?: floatval($user->agent4_rate)) / 100;
                        $order->user_agent5_rate = (floatval($user->transfer_agent5_rate) ?: floatval($user->agent5_rate)) / 100;
                    }
                    if($order->type == 1){
                        $order->user_agent1_rate = (floatval($user->settlement_agent1_rate) ?: floatval($user->agent1_rate)) / 100;
                        $order->user_agent2_rate = (floatval($user->settlement_agent2_rate) ?: floatval($user->agent2_rate)) / 100;
                        $order->user_agent3_rate = (floatval($user->settlement_agent3_rate) ?: floatval($user->agent3_rate)) / 100;
                        $order->user_agent4_rate = (floatval($user->settlement_agent4_rate) ?: floatval($user->agent4_rate)) / 100;
                        $order->user_agent5_rate = (floatval($user->settlement_agent5_rate) ?: floatval($user->agent5_rate)) / 100;
                    }

                    $order->pay_certificate_1 = $request->input('pay_certificate_1');
                    $order->pay_certificate_2 = $request->input('pay_certificate_2');
                    $order->pay_certificate_3 = $request->input('pay_certificate_3');
                    $order->user_agent1_id = $user->pid;
                    if($user->pid > 0){
                        $user_row = \App\Models\User::select(['id','name','pid'])->find($user->id);
                        if($user_row){
                            $user_parent = $user_row->getAncestors()->toArray();
                            if(!empty($user_parent)){
                                usort($user_parent, function($a, $b) {
                                    return $a['level'] - $b['level'];
                                });
                                $order->user_agent2_id = $user_parent[1]['id'] ?? 0;
                                $order->user_agent3_id = $user_parent[2]['id'] ?? 0;
                                $order->user_agent4_id = $user_parent[3]['id'] ?? 0;
                                $order->user_agent5_id = $user_parent[4]['id'] ?? 0;
                            }
                        }
                    }
                    $order->actual_amount = $order->amount;
                    if(intval(bob_admin_setting("base_user_confirm_transfer_order_confirmed_status")) == 0){
                        $order->pay_status = 2;
                        $order->save();
                        if($order->type == 0){
                            $transferOrderSuccessService = App::makeWith(TransferOrderSuccessService::class,['filename'=>LogConstService::TRANSFER_ORDER_LOG_PREFIX.$order->id]);
                            $transferOrderSuccessService->excute($order->id,$order->amount);
                        }
                        if($order->type == 1){
                            $settlementOrderSuccessService = App::makeWith(SettlementOrderSuccessService::class,['filename'=>LogConstService::TRANSFER_ORDER_LOG_PREFIX.$order->id]);
                            $settlementOrderSuccessService->excute($order->id,$order->amount);
                        }
                        $createTransferOrderLogService = App::makeWith(CreateTransferOrderLogService::class,['filename'=>LogConstService::TRANSFER_ORDER_LOG_PREFIX.$order->id]);
                        $createTransferOrderLogService->excute($order->id,"金主完成订单","金主：".$user->bname);
                    }else{
                        $order->pay_status = 3;
                        $order->save();
                        $createTransferOrderLogService = App::makeWith(CreateTransferOrderLogService::class,['filename'=>LogConstService::TRANSFER_ORDER_LOG_PREFIX.$order->id]);
                        $createTransferOrderLogService->excute($order->id,"金主确认代付，提交后台，等待确认","金主：".$user->bname);
                        if($order->type == 0){
                            bob_send_system_transfer_notice(['success_text'=>"代付订单待确认，订单号：".$order->ordernumber,'voice_id'=>'transfer_10','id'=>10]);
                        }
                        if($order->type == 1){
                            bob_send_system_settlement_notice(['success_text'=>"结算订单待确认，订单号：".$order->ordernumber,'voice_id'=>'transfer_10','id'=>10]);
                        }
                    }

                    app(SystemLogService::class)->logAction(
                        actionKey: 'api.v2.users.transfer-orders.submit-order',
                        text: '提交 代付订单',
                        subject: $order,
                        properties: [
                            'user_id' => $request->user()->id,
                            'order_id' => $order->id,
                            'ordernumber' => $order->ordernumber,
                            'amount' => $order->amount,
                            'status' => $order->status,
                            'pay_status' => $order->pay_status,
                        ],
                        remark: sprintf('提交 代付订单 %s', $order->ordernumber),
                        logType: 'operation',
                        actionMethod: 'POST',
                        appType: 'user',
                        user: $request->user()
                    );

                    DB::commit();
                    cache_transfer_info($order);
                    return $this->success();
                }
            }
            throw new \Exception("非法操作");
        }catch (\Exception $e){
            DB::rollBack();
            return $this->error("订单已失效");
        }
    }


    public function logs(Request $request)
    {
        $model = new TransferOrder();
        $model = $model->where('user_id', $request->user()->id)->where('status',4);
        if($request->input("ordernumber")){
            $model = $model->where('ordernumber', $request->input("ordernumber"));
        }
        if($request->input("time") == 1){
            $model = $model->where('created_at','>=', date('Y-m-d')." 00:00:00")->where('created_at','<=', date('Y-m-d')." 23:59:59");
        }
        $result = $model->select($this->field)->orderBy('id','desc')->paginate($this->pageSize);
        $this->data['lists'] = TransferOrderCollection::make($result);
        return $this->success('', $this->data);
    }

    public function uploadImage(UploadImageRequest $request)
    {
        $path = $request->file('file')->store('transfer', 'public');
        $this->data['path'] = $path;
        $this->data['url'] = Storage::disk('public')->url($this->data['path']);
        return $this->success("上传成功", $this->data);
    }
}

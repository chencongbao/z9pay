<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Models\TransferOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use App\Services\Const\LogConstService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\TransferOrder\TransferOrderFailService;
use App\Services\TransferOrder\TransferOrderSuccessService;
use App\Services\SettlementOrder\SettlementOrderSuccessService;
use App\Services\Report\OrderStatusReportRepairService;
use App\Services\TransferOrderLog\CreateTransferOrderLogService;

class QueryTransferOrderStatusAndHandleJob implements ShouldQueue,ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    public $id = 0;

    public function uniqueFor(): int
    {
        return 10; // 10s内同一订单只允许一个job
    }

    public function uniqueId()
    {
        return 'QueryTransferOrderStatusAndHandleJob_' . $this->id;
    }

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order_id = 0)
    {
        $this->id = $order_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DB::transaction(function (){
            $order = TransferOrder::where('id',$this->id)->where('status',2)->lockForUpdate()->first(['id', 'type', 'status', 'remark', 'ordernumber', 'channel_id']);
            if($order){
                $createTransferOrderLogService = App::makeWith(CreateTransferOrderLogService::class,['filename'=>LogConstService::TRANSFER_ORDER_LOG_PREFIX.$order->id]);
                $createTransferOrderLogService->excute($order->id,"代付订单轮询查单，开始查询",[]);
                $channel = Channel::find($order->channel_id);
                $classname = 'Richard\\Payment\\Channel\\' . $channel->classname;
                $payment = new $classname(LogConstService::TRANSFER_ORDER_LOG_PREFIX.$order->id);
                $result = $payment->queryTransferStatus($order->ordernumber);
                if(!empty($result)){
                    if(isset($result['callback_order_status'])){
                        if($result['callback_order_status'] == 1){
                            if($order->type == 0){
                                $transferOrderSuccessService = App::makeWith(TransferOrderSuccessService::class,['filename'=>LogConstService::TRANSFER_ORDER_LOG_PREFIX.$order->id]);
                                $transferOrderSuccessService->excute($order->id,floatval($result['callback_order_amount']));
                            }
                            if($order->type == 1){
                                $settlementOrderSuccessService = App::makeWith(SettlementOrderSuccessService::class,['filename'=>LogConstService::TRANSFER_ORDER_LOG_PREFIX.$order->id]);
                                $settlementOrderSuccessService->excute($order->id,floatval($result['callback_order_amount']));
                            }
                            $createTransferOrderLogService->excute($order->id,"第三方代付成功查询订单信息",$result);
                        }
                        if($result['callback_order_status'] == 2){
                            dispatch(new QueryTransferOrderStatusAndHandleJob($order->id))->delay(now()->addSeconds(10))->onQueue('query');
                        }
                        if($result['callback_order_status'] == 3){
                            if($order->type == 0){
                            if(intval(config("other.transfer_pending_status",1)) == 1){
                                    $order->status = 3;
                                    $order->remark = $payment->error ?: "第三方代付失败";
                                    $order->save();
                                    cache_transfer_info($order);
                                    App::make(OrderStatusReportRepairService::class)->forTransferOrder($order);
                                    bob_send_system_transfer_notice(['success_text'=>"代付订单处理中，订单号：".$order->ordernumber,'voice_id'=>'transfer_6','id'=>6]);
                                }else{
                                    App::makeWith(TransferOrderFailService::class,['filename'=>LogConstService::TRANSFER_ORDER_LOG_PREFIX.$order->id])->excute($order->id,$payment->error ?: "第三方代付失败");
                                }
                            }
                            if($order->type == 1){
                                $order->status = 3;
                                $order->remark = $payment->error ?: "第三方代付失败";
                                $order->save();
                                cache_transfer_info($order);
                                App::make(OrderStatusReportRepairService::class)->forTransferOrder($order);
                            }
                            $createTransferOrderLogService->excute($order->id,"第三方代付失败提示",$payment->error ?: "第三方代付失败");
                        }
                    }
                }
            }
        });
    }
}

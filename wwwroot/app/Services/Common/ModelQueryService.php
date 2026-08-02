<?php

namespace App\Services\Common;

use App\Models\DepositOrder;
use App\Models\MerchantUser;
use App\Models\UserRelation;
use App\Models\AgentUserRelation;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
use App\Services\Order\OrderCacheService;
use App\Services\Cache\DepositOrder\CacheDepositOrderInfoService;

class ModelQueryService
{

    public function excute(Model $model,$where = [])
    {
        // 服务端传入的强制条件优先，避免请求参数覆盖 mid/type/status 等隔离条件。
        $where = $this->filterEmptyValuesRecursive(array_merge(request()->all(), $where));
        $table = $model->getTable();

        //username
        if(isset($where['username'])){
            if($table == 'merchant_infos'){
                $model = $model->whereIn('merchant_user_id',MerchantUser::where('username',$where['username'])->pluck("id"));
            }else{
                $model = $model->where('username',$where['username']);
            }
        }

        //id
        if(isset($where['id'])){
            if($table == 'merchant_infos'){
                $model = $model->where('merchant_user_id',$where['id']);
            }else{
                $model = $model->where('id',$where['id']);
            }
        }

        //name
        if(isset($where['name'])){
            $model = $model->where('name',$where['name']);
        }

        //coder
        if(isset($where['coder'])){
            $model = $model->where('coder',$where['coder']);
        }

        //status
        if(isset($where['status'])){
            if($table == 'merchant_infos'){
                $model = $model->whereIn('merchant_user_id',MerchantUser::where('status',$where['status'])->pluck("id"));
            }else{
                $model = $model->where('status',$where['status']);
            }
        }

        //currency_id
        if(isset($where['currency_id'])){
            $model = $model->where('currency_id',$where['currency_id']);
        }

        //agent_user_id
        if(isset($where['agent_user_id'])){
            if($table == 'merchant_infos'){
                $model = $model->whereIn('agent_user_id',AgentUserRelation::where('parent_id',$where['agent_user_id'])->get()->pluck('child_id'));
            }else{
                $model = $model->where('agent_user_id',$where['agent_user_id']);
            }
        }

        //type
        if(isset($where['type'])){
            $model = $model->where('type',$where['type']);
        }

        //created_at-start
        if(isset($where['created_at']['start'])){
            $model = $model->where('created_at','>=',$where['created_at']['start']);
        }

        //created_at-end
        if(isset($where['created_at']['end'])){
            $model = $model->where('created_at','<=',$where['created_at']['end']);
        }

        //ordernumber
        if(isset($where['ordernumber'])){
            if($table == 'merchant_balance_logs'){
                if(mb_strpos($where['ordernumber'], "T")  === 0){
                    $result = App::make(OrderCacheService::class)->getTransferByOrdernumber($where['ordernumber']);
                    if(!empty($result) && isset($result['id'])){
                        $model = $model->where('type_id',$result['id'])->where('type','>=',2)->where('type','<',6);
                    }
                }
                if(mb_strpos($where['ordernumber'], "D")  === 0){
                    $result = App::make(CacheDepositOrderInfoService::class)->excute($where['ordernumber']);
                    if(!empty($result) && isset($result['id'])){
                        $model = $model->where('type_id',$result['id'])->where('type',1);
                    }
                }
                if(mb_strpos($where['ordernumber'], "S")  === 0){
                    $result = App::make(OrderCacheService::class)->getTransferByOrdernumber($where['ordernumber']);
                    if(!empty($result) && isset($result['id'])){
                        $model = $model->where('type_id',$result['id'])->whereIn('type',[6,7,8,5]);
                    }
                }
            }else{
                $model = $model->where('ordernumber',$where['ordernumber']);
            }
        }

        //mid
        if(isset($where['mid'])){
            $model = $model->where('mid',$where['mid']);
        }

        //agent_id
        if(isset($where['agent_id'])){
            $model = $model->where('agent_id',$where['agent_id']);
        }


        //user_id
        if(isset($where['user_id'])){
            $model = $model->where('user_id',$where['user_id']);
        }

        //callback
        if(isset($where['callback_status'])){
            $model = $model->where('callback_status',$where['callback_status']);
        }


        //order_no
        if(isset($where['order_no'])){
            if($table == 'merchant_balance_logs' && isset($where['mid'])){
                $result = App::make(OrderCacheService::class)->getTransferByMerchantOrder($where['mid'], $where['order_no']);
                if(!empty($result) && isset($result['id'])){
                    $model = $model->where('type_id',$result['id'])->where('type','>=',2)->where('type','<',9);
                }else{
                    $result = App::make(CacheDepositOrderInfoService::class)->excute($where['order_no'],$where['mid']);
                    if(!empty($result) && isset($result['id'])){
                        $model = $model->where('type_id',$result['id'])->where('type',1);
                    }
                }
            }else{
                $model = $model->where('order_no',$where['order_no']);
            }
        }

        //channel_id
        if(isset($where['channel_id'])){
            $model = $model->where('channel_id',$where['channel_id']);
        }

        //payment_id
        if(isset($where['payment_id'])){
            $model = $model->where('payment_id',$where['payment_id']);
        }

        //user_bank_id
        if(isset($where['user_bank_id'])){
            $model = $model->where('user_bank_id',$where['user_bank_id']);
        }

        //merchant_info-currency_id
        if(isset($where['merchant_info']['currency_id'])){
            $model = $model->where('currency_id',$where['merchant_info']['currency_id']);
        }

        //merchant_info-name
        if(isset($where['merchant_info']['name'])){
            $model = $model->where('name',$where['merchant_info']['name']);
        }

        //merchant_info-coder
        if(isset($where['merchant_info']['coder'])){
            $model = $model->where('coder',$where['merchant_info']['coder']);
        }

        //success_time-start
        if(isset($where['success_time']['start'])){
            $model = $model->where('success_time','>=',strtotime($where['success_time']['start']));
        }

        //success_time-end
        if(isset($where['success_time']['end'])){
            $model = $model->where('success_time','<=',strtotime($where['success_time']['end']));
        }


        //unfreeze_time_time-start
        if(isset($where['unfreeze_time']['start'])){
            $model = $model->where('unfreeze_time','>=',strtotime($where['unfreeze_time']['start']));
        }

        //unfreeze_time_time-end
        if(isset($where['unfreeze_time']['end'])){
            $model = $model->where('unfreeze_time','<=',strtotime($where['unfreeze_time']['end']));
        }

        //user_agent_id
        if(isset($where['user_agent_id'])){
            $model = $model->whereIn('user_id',UserRelation::where('parent_id',$where['user_agent_id'])->get()->pluck('child_id'));
        }

        //merchant_agent_id
        if(isset($where['merchant_agent_id'])){
            $model = $model->whereIn('merchant_agent1_id',AgentUserRelation::where('parent_id',$where['merchant_agent_id'])->get()->pluck('child_id'));
        }

        //pay_name
        if(isset($where['pay_name'])){
            $model = $model->where('pay_name',$where['pay_name']);
        }

        //user_bank_id
        if(isset($where['user_bank_id'])){
            $model = $model->where('user_bank_id',$where['user_bank_id']);
        }

        //unfreeze_time-start
        if(isset($where['unfreeze_time']['start'])){
            $model = $model->where('unfreeze_time','>=',strtotime($where['unfreeze_time']['start']));
        }

        //unfreeze_time-end
        if(isset($where['unfreeze_time']['end'])){
            $model = $model->where('unfreeze_time','<=',strtotime($where['unfreeze_time']['end']));
        }

        //begin_date
        if(isset($where['begin_date'])){
            $model = $model->where('created_at','>=',$where['begin_date']);
        }

        //end_date
        if(isset($where['end_date'])){
            $model = $model->where('created_at','<=',$where['end_date']);
        }

        //is_agent
        if(isset($where['is_agent'])){
            $model = $model->where('is_agent',$where['is_agent']);
        }

        //collection_card_no
        if(isset($where['collection_card_no'])){
            $model = $model->where('collection_card_no',$where['collection_card_no']);
        }

        //hand_success
        if(isset($where['hand_success'])){
            $model = $model->where('hand_success',$where['hand_success']);
        }

        //amount
        if(isset($where['amount'])){
            $model = $model->where('amount',$where['amount']);
        }

        //pay_amount
        if(isset($where['pay_amount'])){
            $model = $model->where('pay_amount',$where['pay_amount']);
        }

        //actual_amount
        if(isset($where['actual_amount'])){
            $model = $model->where('actual_amount',$where['actual_amount']);
        }

        //bank_id
        if(isset($where['bank_id'])){
            $model = $model->where('bank_id',$where['bank_id']);
        }

        //card_no
        if(isset($where['card_no'])){
            $model = $model->where('card_no',$where['card_no']);
        }

        //holder_name
        if(isset($where['holder_name'])){
            $model = $model->where('holder_name',$where['holder_name']);
        }

        if(isset($where['freeze_ordernumber'])){
            $deposit_result = DepositOrder::where('ordernumber',$where['freeze_ordernumber'])->first(["id"]);
            if($deposit_result){
                $model = $model->where('deposit_order_id',$deposit_result->id);
            }
        }

        if(isset($where['freeze_order_no'])){
            $deposit_result = DepositOrder::where('order_no',$where['freeze_order_no'])->first(["id"]);
            if($deposit_result){
                $model = $model->where('deposit_order_id',$deposit_result->id);
            }
        }

        return $model;
    }


    private function filterEmptyValuesRecursive(array $array): array
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->filterEmptyValuesRecursive($value);
            }
        }

        if(!empty($array['created_at']) && !empty($array['begin_date']) && !empty($array['end_date'])){
            unset($array['created_at']);
        }

        return array_filter($array, function ($value) {
            return !is_null($value) && $value !== '';
        });
    }
}

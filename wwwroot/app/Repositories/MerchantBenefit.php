<?php

namespace App\Repositories;

use App\Models\DepositOrder;
use App\Models\MerchantBalanceLog;
use App\Models\MerchantInfo;
use App\Models\TransferOrder;
use Dcat\Admin\Grid\Model;
use Dcat\Admin\Repositories\Repository;

class MerchantBenefit extends Repository
{

    public function get(Model $model)
    {
        $begin_date = date('Y-m-d') . " 00:00:00";
        $end_date = date('Y-m-d',strtotime('+1 day')) . " 00:00:00";
        if (request('created_at')['start'] && request('created_at')['end']) {
            $begin_date = request('created_at')['start'];
            $end_date = request('created_at')['end'];
        }

        $data = [];
        $result = MerchantInfo::where('merchant_user_id',1)->get();
        if(!$result->isEmpty()){
            foreach($result as $item){
                $data[] = [
                    'merchant_name' => $item->bname,
                    'deposit_success_order' => $this->queryDeposit(['begin_date'=>$begin_date,'end_date'=>$end_date,'status'=>5,'mid'=>$item->merchant_user_id],1),
                    'deposit_success_amount' => $this->queryDeposit(['begin_date'=>$begin_date,'end_date'=>$end_date,'status'=>5,'mid'=>$item->merchant_user_id]),
                    'deposit_merchant_fee' => $this->queryDeposit(['begin_date'=>$begin_date,'end_date'=>$end_date,'status'=>5,'mid'=>$item->merchant_user_id],0,'merchant_fee'),
                    'transfer_success_order' => $this->queryTransfer(['begin_date'=>$begin_date,'end_date'=>$end_date,'status'=>4,'mid'=>$item->merchant_user_id,'type'=>0],1),
                    'transfer_success_amount' =>$this->queryTransfer(['begin_date'=>$begin_date,'end_date'=>$end_date,'status'=>4,'mid'=>$item->merchant_user_id,'type'=>0]),
                    'transfer_merchant_fee' => $this->queryTransfer(['begin_date'=>$begin_date,'end_date'=>$end_date,'status'=>4,'mid'=>$item->merchant_user_id,'type'=>0],0,'merchant_fee'),
                    'settlement_success_order' => $this->queryTransfer(['begin_date'=>$begin_date,'end_date'=>$end_date,'status'=>4,'mid'=>$item->merchant_user_id,'type'=>1],1),
                    'settlement_success_amount' =>$this->queryTransfer(['begin_date'=>$begin_date,'end_date'=>$end_date,'status'=>4,'mid'=>$item->merchant_user_id,'type'=>1]),
                    'settlement_merchant_fee' => $this->queryTransfer(['begin_date'=>$begin_date,'end_date'=>$end_date,'status'=>4,'mid'=>$item->merchant_user_id,'type'=>1],0,'merchant_fee'),
                    'amount1' => $this->queryAmount1($item->merchant_user_id,$begin_date,$end_date),
                    'amount2' => $this->queryAmount2($item->merchant_user_id,$begin_date,$end_date),
                    'amount3' => $this->queryAmount3($item->merchant_user_id,$begin_date,$end_date)
                ];
            }
        }
        return $data;
    }

    private function queryAmount3($mid,$begin_date,$end_date){
        $fee1 = DepositOrder::where('mid', $mid)->where('created_at', '>=', $begin_date)->where('created_at', '<=', $end_date)->where('status', 5)->sum('merchant_fee');
        $fee2 = TransferOrder::where('mid', $mid)->where('created_at', '>=', $begin_date)->where('created_at', '<=', $end_date)->where('status', 4)->sum('merchant_fee');
        return $fee1 + $fee2;
    }

    private function queryAmount2($mid,$begin_date,$end_date){
        return MerchantBalanceLog::where('mid', $mid)->where('created_at', '>=', $begin_date)->where('created_at', '<=', $end_date)->where('type', 12)->sum('amount');
    }


    private function queryAmount1($mid,$begin_date,$end_date){
        return MerchantBalanceLog::where('mid', $mid)->where('created_at', '>=', $begin_date)->where('created_at', '<=', $end_date)->whereIN('type',[5,11])->sum('amount');
    }

    private function queryDeposit($data = [],$count = 1,$field = 'actual_amount')
    {
        $model = new DepositOrder();
        if(isset($data['status'])){
            $model = $model->where('status',$data['status']);
        }
        if(isset($data['mid'])){
            $model = $model->where('mid',$data['mid']);
        }
        if(isset($data['channel_id'])){
            $model = $model->where('channel_id',$data['channel_id']);
        }
        if(isset($data['payment_id'])){
            $model = $model->where('payment_id',$data['payment_id']);
        }
        if(isset($data['begin_date'])){
            $model = $model->where('created_at','>=',$data['begin_date']);
        }
        if(isset($data['end_date'])){
            $model = $model->where('created_at','<=',$data['end_date']);
        }
        if(isset($data['user_id'])){
            $model = $model->where('user_id',$data['user_id']);
        }
        if($count == 1){
            return $model->count();
        }
        return $model->sum($field);
    }


    private function queryTransfer($data = [],$count = 1,$field = 'actual_amount')
    {
        $model = new TransferOrder();
        if(isset($data['status'])){
            $model = $model->where('status',$data['status']);
        }
        if(isset($data['mid'])){
            $model = $model->where('mid',$data['mid']);
        }
        if(isset($data['channel_id'])){
            $model = $model->where('channel_id',$data['channel_id']);
        }
        if(isset($data['payment_id'])){
            $model = $model->where('payment_id',$data['payment_id']);
        }
        if(isset($data['begin_date'])){
            $model = $model->where('created_at','>=',$data['begin_date']);
        }
        if(isset($data['end_date'])){
            $model = $model->where('created_at','<=',$data['end_date']);
        }
        if(isset($data['user_id'])){
            $model = $model->where('user_id',$data['user_id']);
        }
        if($count == 1){
            return $model->count();
        }
        return $model->sum($field);
    }
}

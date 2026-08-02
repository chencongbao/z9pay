<?php

namespace App\Services\MerchantChannel;

use App\Models\MerchantChannel;
use App\Traits\ServiceTraits;

class CheckMerchantChannelWhereService
{
    use ServiceTraits;

    public function excute($mid = 0,$channel_id = 0,$amount = 0)
    {
        $result = MerchantChannel::where('merchant_user_id',$mid)->where('channel_id',$channel_id)->where('payment_id',7)->where('status',1)->first();
        if($result){
            if($result->collection_min_amount > 0 && $amount < $result->collection_min_amount){
                throw new \Exception("代付金额小于渠道单笔下限");
            }
            if($result->collection_max_amount > 0 && $amount > $result->collection_max_amount){
                throw new \Exception("代付金额大于渠道单笔上限");
            }
            return $result->fee;
        }
        throw new \Exception("此商户不支持当前代付渠道");
    }
}

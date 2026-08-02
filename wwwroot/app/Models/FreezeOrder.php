<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class FreezeOrder extends Model
{
	use ModelTraits;
    protected $table = 'freeze_orders';

    protected $guarded = [];

    public function merchant_info(){
        return $this->belongsTo(MerchantInfo::class,'mid');
    }

    public function deposit_order(){
        return $this->belongsTo(DepositOrder::class,'deposit_order_id');
    }

    public function user(){
        return $this->belongsTo(User::class,'user_id');
    }

}

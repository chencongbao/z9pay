<?php

namespace App\Models;



use App\Traits\ActivityLogTrait;
use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantPayment extends Model
{
	use ModelTraits, SoftDeletes, ActivityLogTrait;

    protected $table = 'merchant_payments';

    protected $appends = ['payment_name'];

    protected $casts = [
        'transfer_rates' => 'array',
    ];

    public function merchant_info(){
        return $this->belongsTo(MerchantInfo::class,'merchant_user_id');
    }

    public function agent_user_relation(){
        return $this->hasMany(AgentUserRelation::class,'child_id','agent_user_id');
    }

    public function getPaymentNameAttribute()
    {
        return bob_get_value_by_id_array(['id' => $this->payment_id], 'name', config('payment'));
    }

}

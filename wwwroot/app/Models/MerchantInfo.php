<?php

namespace App\Models;


use App\Traits\ModelTraits;
use App\Traits\ActivityLogTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantInfo extends Model
{
    use ModelTraits, SoftDeletes, ActivityLogTrait;

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'merchant_user_id';

    protected $appends = ["bname"];

    protected $guarded = [];

    public function merchant_user()
    {
        return $this->belongsTo(MerchantUser::class,'merchant_user_id');
    }

    public function agent_user()
    {
        return $this->belongsTo(AgentUser::class,'agent_user_id');
    }

    public function getBnameAttribute(){
        $currency = bob_get_value_by_id_array(['id'=>$this->currency_id], 'name', config('default.currency'));
        return "【#".$this->merchant_user_id."】【".$currency."】".$this->name;
    }
}

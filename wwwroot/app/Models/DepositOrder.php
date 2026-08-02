<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class DepositOrder extends Model
{
    use ModelTraits;

    protected $table = 'deposit_orders';

    protected $guarded = [];

    protected $appends = ["qrcode_url_format"];

    protected $casts = [
        'collection_app_info' => 'array',
    ];

    public function getQrcodeUrlFormatAttribute()
    {
        return $this->collection_qrcode;
    }

    public function bank_codes(){
        return $this->belongsTo(BankCode::class,'bank_id');
    }

    public function merchant_info(){
        return $this->belongsTo(MerchantInfo::class,'mid');
    }

    public function user_relation(){
        return $this->hasMany(UserRelation::class,'child_id','user_id');
    }

    public function user(){
        return $this->belongsTo(User::class,"user_id")->select('id','name');
    }

    public function channel(){
        return $this->belongsTo(Channel::class,'channel_id');
    }

    public function admin(){
        return $this->belongsTo(AdminUser::class,'hand_admin_id');
    }



}

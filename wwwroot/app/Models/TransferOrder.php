<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class TransferOrder extends Model
{
	use ModelTraits;
    protected $table = 'transfer_orders';

    protected $guarded = [];

    protected $casts = [
        'channel_info' => 'array',
        'transfer_order_confirm_remark' => 'array',
    ];

    public function merchant_info(){
        return $this->belongsTo(MerchantInfo::class,'mid');
    }

    public function user(){
        return $this->belongsTo(User::class,"user_id");
    }

    public function merchant(){
        return $this->belongsTo(MerchantUser::class,'merchant_action_id');
    }

    public function admin(){
        return $this->belongsTo(AdminUser::class,'hand_admin_id');
    }

    public function bank(){
        return $this->belongsTo(BankCode::class,'bank_id');
    }

    public function user_relation(){
        return $this->hasMany(UserRelation::class,'child_id','user_id');
    }

    public function channel(){
        return $this->belongsTo(Channel::class,'channel_id');
    }

}

<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class MerchantBalanceLog extends Model
{
	use ModelTraits;
    protected $table = 'merchant_balance_logs';

    protected $guarded = [];

    public function merchant_info(){
        return $this->belongsTo(MerchantInfo::class,'mid','merchant_user_id');
    }

    public function admin_user()
    {
        return $this->belongsTo(AdminUser::class,'admin_id');
    }



}

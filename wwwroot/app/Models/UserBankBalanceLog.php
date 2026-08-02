<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class UserBankBalanceLog extends Model
{
    use ModelTraits;

    protected $guarded = [];

    public function user(){
        return $this->belongsTo(User::class,"user_id")->select('id','name');
    }


    public function user_bank(){
        return $this->belongsTo(UserBank::class,'user_bank_id');
    }

    public function admin(){
        return $this->belongsTo(AdminUser::class,'action_admin_id');
    }
}

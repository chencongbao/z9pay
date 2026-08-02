<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class UserDepositDetail extends Model
{
    use ModelTraits;

    protected $guarded = [];

    public function admin(){
        return $this->belongsTo(AdminUser::class,'admin_id');
    }
}

<?php

namespace App\Models;

use App\Traits\ModelTraits;

use Illuminate\Database\Eloquent\Model;

class UserBalanceLog extends Model
{
	use ModelTraits;

    protected $table = 'user_balance_logs';

    protected $guarded = [];

    protected $appends = ['type_text'];

    public function user_relation(){
        return $this->hasMany(UserRelation::class,'child_id','user_id');
    }

    public function user(){
        return $this->belongsTo(User::class,'user_id')->select('id','name');
    }

    public function getTypeTextAttribute()
    {
        return $this->is_agent == 1 ? optional(config('default.agent_balance_type'))->offsetGet($this->type)  : optional(config('default.user_balance_type'))->offsetGet($this->type);
    }

    public function admin(){
        return $this->belongsTo(AdminUser::class,'action_user_id');
    }

}

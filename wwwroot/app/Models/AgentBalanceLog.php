<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentBalanceLog extends Model
{
	use ModelTraits,SoftDeletes;


    protected $table = 'agent_balance_logs';

    protected $guarded = [];

    public function agent_user_relation(){
        return $this->hasMany(AgentUserRelation::class,'child_id','agent_id');
    }

    public function agent_user(){
        return $this->belongsTo(AgentUser::class,'agent_id');
    }

    public function merchant_info(){
        return $this->belongsTo(MerchantInfo::class,'mid');
    }

    public function admin(){
        return $this->belongsTo(AdminUser::class,'action_agent_id');
    }

}

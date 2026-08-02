<?php

namespace App\Models;

use App\Traits\ActivityLogTrait;
use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserModel extends Model
{

    use ModelTraits, SoftDeletes, ActivityLogTrait;

    protected $table = "users";

    protected $appends = ['bname'];

    public function parent_user(){
        return $this->belongsTo(UserModel::class,'pid');
    }

    public function getBnameAttribute(){
        return "(#".$this->id.")".$this->name;
    }

    public function user_relation(){
        return $this->hasMany(UserRelation::class,'child_id','pid');
    }
}

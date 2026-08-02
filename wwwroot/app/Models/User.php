<?php

namespace App\Models;

use App\Traits\ActivityLogTrait;
use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Jiaxincui\ClosureTable\Traits\ClosureTable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable,SoftDeletes;

    use ClosureTable, ModelTraits, ActivityLogTrait;

    protected $guarded = [];

    protected $closureTable = 'user_relations';
    protected $ancestorColumn = 'parent_id';
    protected $descendantColumn = 'child_id';
    protected $distanceColumn = 'level';
    protected $parentColumn = 'pid';

    protected $appends = ['bname'];

    protected $casts = [
        'user_deposit_payment_rate' => 'array',
    ];

    public function parent_user(){
        return $this->belongsTo(User::class,'pid');
    }

    public function user_relation(){
        return $this->hasMany(UserRelation::class,'child_id','pid');
    }

    public function getBnameAttribute()
    {
        $username = trim((string)($this->username ?? ''));
        $name = trim((string)($this->name ?? ''));
        $title = "【#{$this->id}】";

        if ($username !== '') {
            $title .= "【{$username}】";
        }

        return $title . $name;
    }
}

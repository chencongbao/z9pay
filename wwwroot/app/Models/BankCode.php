<?php

namespace App\Models;

use App\Traits\ActivityLogTrait;
use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class BankCode extends Model
{
    use ModelTraits, ActivityLogTrait;

    public $timestamps = false;

    protected $appends = ['bname'];

    protected $guarded = [];

    public function getBnameAttribute()
    {
        return $this->name."【".$this->code."】";
    }
}

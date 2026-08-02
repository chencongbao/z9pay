<?php

namespace App\Models;

use App\Traits\ActivityLogTrait;
use Dcat\Admin\Traits\HasDateTimeFormatter;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
	use HasDateTimeFormatter, ActivityLogTrait;


   protected $appends = ['bname'];


    public function getBnameAttribute(){
        return "【#".$this->id."】【".$this->code."】".$this->name;
    }
}

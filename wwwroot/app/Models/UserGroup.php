<?php

namespace App\Models;

use App\Traits\ActivityLogTrait;
use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class UserGroup extends Model
{
	use ModelTraits, ActivityLogTrait;
    protected $table = 'user_groups';

}

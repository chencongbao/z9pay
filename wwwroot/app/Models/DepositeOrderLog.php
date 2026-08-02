<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class DepositeOrderLog extends Model
{
	use ModelTraits;
    protected $table = 'deposite_order_logs';

    protected $guarded = [];

}

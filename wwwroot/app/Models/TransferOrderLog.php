<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class TransferOrderLog extends Model
{
	use ModelTraits;
    protected $table = 'transfer_order_logs';
    protected $guarded = [];

}

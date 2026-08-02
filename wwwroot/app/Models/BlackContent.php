<?php

namespace App\Models;

use App\Traits\ActivityLogTrait;
use Dcat\Admin\Traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;

class BlackContent extends Model
{
	use HasDateTimeFormatter;
    use ActivityLogTrait;

    protected $table = 'black_contents';

    public function merchant_info()
    {
        return $this->belongsTo(MerchantInfo::class,'mid');
    }

}

<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class ChannelBankCode extends Model
{
    use ModelTraits;

    protected $guarded = [];

    public function channel()
    {
        return $this->belongsTo(Channel::class,'channel_id');
    }
}

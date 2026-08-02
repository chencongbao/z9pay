<?php

namespace App\Models;

use App\Traits\ActivityLogTrait;
use Dcat\Admin\Traits\HasDateTimeFormatter;

use Illuminate\Database\Eloquent\Model;

class ChannelAccount extends Model
{
    use HasDateTimeFormatter, ActivityLogTrait;

    protected $table = 'channel_accounts';

    protected $appends = ['params_format'];

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function getParamsFormatAttribute()
    {
        $data = [];
        if (empty($this->params)) {
            return $data;
        }
        $params = explode("\r\n", $this->params);
        if (!empty($params) && is_array($params)) {
            foreach ($params as $k => $v) {
                $keys = explode("=", $v);
                if (!empty($keys) && is_array($keys) && count($keys) == 2) {
                    if (!empty($keys[0]) && !empty($keys[1])) $data[trim($keys[0])] = trim($keys[1]);
                }
            }
        }
        return $data;
    }
}

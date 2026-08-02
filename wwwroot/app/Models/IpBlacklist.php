<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class IpBlacklist extends Model
{
    use ModelTraits;

    protected $table = 'ip_blacklists';

    protected $fillable = [
        'ip',
        'type',
        'status',
        'reason',
        'remark',
        'hit_count',
        'hit_usernames',
        'locked_at',
        'expires_at',
    ];

    protected $casts = [
        'hit_count' => 'integer',
        'locked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}

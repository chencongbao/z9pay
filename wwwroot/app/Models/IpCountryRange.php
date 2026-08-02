<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Illuminate\Database\Eloquent\Model;

class IpCountryRange extends Model
{

    use ModelTraits;

    protected $table = 'ip_country_ranges';

    protected $fillable = [
        'country_code',
        'begin_ip',
        'end_ip',
        'begin_long',
        'end_long',
        'total_count',
        'cdf_end',
    ];
}

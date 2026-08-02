<?php

namespace App\Models;

use App\Traits\ModelTraits;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    use ModelTraits;

    protected $casts = [
        'properties'     => 'collection',
        'request_input'  => 'array',
    ];
}

<?php

namespace App\Models;

use App\Traits\ActivityLogTrait;
use Dcat\Admin\Models\Role;

class AdminRole extends Role
{
    use ActivityLogTrait;
}


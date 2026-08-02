<?php

namespace App\Repositories;

use Dcat\Admin\Repositories\EloquentRepository;

class MerchantPermission extends EloquentRepository
{
    public function __construct()
    {
        $this->eloquentClass = config('merchant-admin.database.permissions_model');

        parent::__construct();
    }
}

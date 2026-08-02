<?php

namespace App\Repositories;

use Dcat\Admin\Repositories\EloquentRepository;

class MerchantRole extends EloquentRepository
{
    public function __construct($relations = [])
    {
        $this->eloquentClass = config('merchant-admin.database.roles_model');

        parent::__construct($relations);
    }
}

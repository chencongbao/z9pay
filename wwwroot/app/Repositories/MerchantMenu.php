<?php

namespace App\Repositories;

use Dcat\Admin\Repositories\EloquentRepository;

class MerchantMenu extends EloquentRepository
{
    public function __construct($modelOrRelations = [])
    {
        $this->eloquentClass = config('merchant-admin.database.menu_model');

        parent::__construct($modelOrRelations);
    }
}

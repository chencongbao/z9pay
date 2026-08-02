<?php

namespace App\Repositories;

use Dcat\Admin\Repositories\EloquentRepository;

class AgentPermission extends EloquentRepository
{
    public function __construct()
    {
        $this->eloquentClass = config('agent-admin.database.permissions_model');

        parent::__construct();
    }
}

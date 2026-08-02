<?php

namespace App\Repositories;

use Dcat\Admin\Repositories\EloquentRepository;

class AgentRole extends EloquentRepository
{
    public function __construct($relations = [])
    {
        $this->eloquentClass = config('agent-admin.database.roles_model');

        parent::__construct($relations);
    }
}

<?php

namespace App\Repositories;

use Dcat\Admin\Repositories\EloquentRepository;

class AgentMenu extends EloquentRepository
{
    public function __construct($modelOrRelations = [])
    {
        $this->eloquentClass = config('agent-admin.database.menu_model');

        parent::__construct($modelOrRelations);
    }
}

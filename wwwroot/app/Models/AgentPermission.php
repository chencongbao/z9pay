<?php

namespace App\Models;

use Dcat\Admin\Models\Permission;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AgentPermission extends Permission
{

    public function __construct(array $attributes = [])
    {
        $this->init();

        parent::__construct($attributes);
    }

    protected function init()
    {
        $connection = config('agent-admin.database.connection') ?: config('database.default');

        $this->setConnection($connection);

        $this->setTable(config('agent-admin.database.permissions_table'));
    }

    /**
     * Permission belongs to many roles.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        $pivotTable = config('agent-admin.database.role_permissions_table');

        $relatedModel = config('agent-admin.database.roles_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'permission_id', 'role_id');
    }

    /**
     * @return BelongsToMany
     */
    public function menus(): BelongsToMany
    {
        $pivotTable = config('agent-admin.database.permission_menu_table');

        $relatedModel = config('agent-admin.database.menu_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'permission_id', 'menu_id')->withTimestamps();
    }
}

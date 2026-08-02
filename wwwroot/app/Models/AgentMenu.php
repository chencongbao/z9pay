<?php

namespace App\Models;

use Dcat\Admin\Models\Menu;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AgentMenu extends Menu
{

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->init();
    }

    protected function init()
    {
        $connection = config('agent-admin.database.connection') ?: config('database.default');

        $this->setConnection($connection);

        $this->setTable(config('agent-admin.database.menu_table'));
    }

    public static function withPermission()
    {
        return config('agent-admin.menu.bind_permission') && config('agent-admin.permission.enable');
    }

    /**
     * Determine if enable menu bind role.
     *
     * @return bool
     */
    public static function withRole()
    {
        return (bool) config('agent-admin.permission.enable');
    }

    public function roles(): BelongsToMany
    {
        $pivotTable = config('agent-admin.database.role_menu_table');

        $relatedModel = config('agent-admin.database.roles_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'menu_id', 'role_id')->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        $pivotTable = config('agent-admin.database.permission_menu_table');

        $relatedModel = config('agent-admin.database.permissions_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'menu_id', 'permission_id')->withTimestamps();
    }
}

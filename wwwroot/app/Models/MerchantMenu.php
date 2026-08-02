<?php

namespace App\Models;

use Dcat\Admin\Models\Menu;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MerchantMenu extends Menu
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->init();
    }

    protected function init()
    {
        $connection = config('merchant-admin.database.connection') ?: config('database.default');

        $this->setConnection($connection);

        $this->setTable(config('merchant-admin.database.menu_table'));
    }

    public static function withPermission()
    {
        return config('merchant-admin.menu.bind_permission') && config('merchant-admin.permission.enable');
    }

    /**
     * Determine if enable menu bind role.
     *
     * @return bool
     */
    public static function withRole()
    {
        return (bool) config('merchant-admin.permission.enable');
    }

    public function roles(): BelongsToMany
    {
        $pivotTable = config('merchant-admin.database.role_menu_table');

        $relatedModel = config('merchant-admin.database.roles_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'menu_id', 'role_id')->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        $pivotTable = config('merchant-admin.database.permission_menu_table');

        $relatedModel = config('merchant-admin.database.permissions_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'menu_id', 'permission_id')->withTimestamps();
    }
}

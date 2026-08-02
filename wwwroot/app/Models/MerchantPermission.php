<?php

namespace App\Models;

use Dcat\Admin\Models\Permission;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MerchantPermission extends Permission
{

    public function __construct(array $attributes = [])
    {
        $this->init();

        parent::__construct($attributes);
    }

    protected function init()
    {
        $connection = config('merchant-admin.database.connection') ?: config('database.default');

        $this->setConnection($connection);

        $this->setTable(config('merchant-admin.database.permissions_table'));
    }

    /**
     * Permission belongs to many roles.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        $pivotTable = config('merchant-admin.database.role_permissions_table');

        $relatedModel = config('merchant-admin.database.roles_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'permission_id', 'role_id');
    }

    /**
     * @return BelongsToMany
     */
    public function menus(): BelongsToMany
    {
        $pivotTable = config('merchant-admin.database.permission_menu_table');

        $relatedModel = config('merchant-admin.database.menu_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'permission_id', 'menu_id')->withTimestamps();
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($model) {
            $model->menus()->detach();
        });
    }
}

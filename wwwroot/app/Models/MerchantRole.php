<?php

namespace App\Models;

use Dcat\Admin\Models\Role;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MerchantRole extends Role
{


    /**
     * {@inheritDoc}
     */
    public function __construct(array $attributes = [])
    {
        $this->init();

        parent::__construct($attributes);
    }

    protected function init()
    {
        $connection = config('merchant-admin.database.connection') ?: config('database.default');

        $this->setConnection($connection);

        $this->setTable(config('merchant-admin.database.roles_table'));
    }

    /**
     * A role belongs to many users.
     *
     * @return BelongsToMany
     */
    public function administrators(): BelongsToMany
    {
        $pivotTable = config('merchant-admin.database.role_users_table');

        $relatedModel = config('merchant-admin.database.users_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'role_id', 'user_id');
    }

    public function roles(): BelongsToMany
    {
        $pivotTable = config('merchant-admin.database.role_permissions_table');

        $relatedModel = config('merchant-admin.database.roles_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'permission_id', 'role_id');
    }

    /**
     * A role belongs to many permissions.
     *
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        $pivotTable = config('merchant-admin.database.role_permissions_table');

        $relatedModel = config('merchant-admin.database.permissions_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'role_id', 'permission_id')->withTimestamps();
    }

    /**
     * @return BelongsToMany
     */
    public function menus(): BelongsToMany
    {
        $pivotTable = config('merchant-admin.database.role_menu_table');

        $relatedModel = config('merchant-admin.database.menu_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'role_id', 'menu_id')->withTimestamps();
    }


    /**
     * Get id of the permission by id.
     *
     * @param  array  $roleIds
     * @return \Illuminate\Support\Collection
     */
    public static function getPermissionId(array $roleIds)
    {
        if (! $roleIds) {
            return collect();
        }
        $related = config('merchant-admin.database.role_permissions_table');

        $model = new static();
        $keyName = $model->getKeyName();

        return $model->newQuery()
            ->leftJoin($related, $keyName, '=', 'role_id')
            ->whereIn($keyName, $roleIds)
            ->get(['permission_id', 'role_id'])
            ->groupBy('role_id')
            ->map(function ($v) {
                $v = $v instanceof Arrayable ? $v->toArray() : $v;

                return array_column($v, 'permission_id');
            });
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($model) {
            $model->menus()->detach();
        });
    }
}

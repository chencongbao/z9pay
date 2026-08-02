<?php

namespace App\Models;

use App\Traits\ActivityLogTrait;
use Dcat\Admin\Models\Administrator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MerchantUser extends Administrator
{
    use SoftDeletes, ActivityLogTrait;

    protected $fillable = [];

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->init();
    }

    protected function init()
    {
        $connection = config('merchant-admin.database.connection') ?: config('database.default');

        $this->setConnection($connection);

        $this->setTable(config('merchant-admin.database.users_table'));
    }

    public function getAvatar()
    {
        $avatar = $this->avatar;

        if ($avatar) {
            if (! URL::isValidUrl($avatar)) {
                $avatar = Storage::disk(config('merchant-admin.upload.disk'))->url($avatar);
            }

            return $avatar;
        }

        return admin_asset(config('merchant-admin.default_avatar') ?: '@admin/images/default-avatar.jpg');
    }

    public function roles(): BelongsToMany
    {
        $pivotTable = config('merchant-admin.database.role_users_table');

        $relatedModel = config('merchant-admin.database.roles_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'user_id', 'role_id')->withTimestamps();
    }

    public function merchant_info()
    {
        return $this->hasOne(MerchantInfo::class);
    }
}

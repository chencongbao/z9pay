<?php

namespace App\Models;

use Dcat\Admin\Models\Administrator;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Jiaxincui\ClosureTable\Traits\ClosureTable;
use App\Traits\ActivityLogTrait;

class AgentUser extends Administrator
{

    use ClosureTable, SoftDeletes, ActivityLogTrait;

    protected $fillable = [];

    protected $guarded = [];

    protected $appends = ['title','spread','showCheckbox','bname'];

    protected $closureTable = 'agent_user_relations';
    protected $ancestorColumn = 'parent_id';
    protected $descendantColumn = 'child_id';
    protected $distanceColumn = 'level';
    protected $parentColumn = 'pid';

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->init();
    }

    protected function init()
    {
        $connection = config('agent-admin.database.connection') ?: config('database.default');

        $this->setConnection($connection);

        $this->setTable(config('agent-admin.database.users_table'));
    }

    /**
     * Get avatar attribute.
     *
     * @return mixed|string
     */
    public function getAvatar()
    {
        $avatar = $this->avatar;

        if ($avatar) {
            if (! URL::isValidUrl($avatar)) {
                $avatar = Storage::disk(config('agent-admin.upload.disk'))->url($avatar);
            }

            return $avatar;
        }

        return admin_asset(config('agent-admin.default_avatar') ?: '@admin/images/default-avatar.jpg');
    }

    /**
     * A user has and belongs to many roles.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        $pivotTable = config('agent-admin.database.role_users_table');

        $relatedModel = config('agent-admin.database.roles_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'user_id', 'role_id')->withTimestamps();
    }

    public function parent_user(){
        return $this->belongsTo(AgentUser::class,'pid');
    }



    public function getTitleAttribute()
    {
        return $this->name;
    }

    public function getSpreadAttribute()
    {
        return true;
    }

    public function getShowCheckboxAttribute()
    {
        return true;
    }

    public function getBnameAttribute(){
        return "【#".$this->id."】".$this->name;
    }

}

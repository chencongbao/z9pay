<?php

namespace App\Admin\Repositories;

use App\Models\FreezeOrder as Model;
use Dcat\Admin\Repositories\EloquentRepository;

class FreezeOrder extends EloquentRepository
{
    /**
     * Model.
     *
     * @var string
     */
    protected $eloquentClass = Model::class;


}

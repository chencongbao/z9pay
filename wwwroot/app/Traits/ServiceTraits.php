<?php

namespace App\Traits;


trait ServiceTraits
{
    public $model;

    public $data = [];

    abstract function excute();
}

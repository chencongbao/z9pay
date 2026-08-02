<?php

namespace App\Admin\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Http\Auth\Permission;
use Dcat\Admin\Http\Controllers\AdminController;

class CommonController extends AdminController
{
    protected $translation = null;

    protected $disableDestroy = true;

    protected $disableShow = true;

    protected $disableCreate = false;

    protected $disableEdit = false;

    public function __construct()
    {
        if ($this->translation) {
            Admin::translation($this->translation);
        }
    }

    public function destroy($id)
    {
        $this->denyWhenDisabled($this->disableDestroy);

        return parent::destroy($id);
    }

    public function show($id, Content $content)
    {
        $this->denyWhenDisabled($this->disableShow);

        return parent::show($id, $content);
    }

    public function edit($id, Content $content)
    {
        $this->denyWhenDisabled($this->disableEdit);

        return parent::edit($id, $content);
    }

    public function create(Content $content)
    {
        $this->denyWhenDisabled($this->disableCreate);

        return parent::create($content);
    }

    public function update($id)
    {
        $this->denyWhenDisabled($this->disableEdit);

        return parent::update($id);
    }

    public function store()
    {
        $this->denyWhenDisabled($this->disableCreate);

        return parent::store();
    }

    protected function denyWhenDisabled(bool $disabled): void
    {
        if ($disabled) {
            Permission::error();
        }
    }
}

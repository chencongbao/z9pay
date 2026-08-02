<?php

namespace App\Admin\Extensions\Tools\Agent;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Grid\Tools\AbstractTool;

class CreateButton extends AbstractTool
{

    protected $grid;

    public function __construct(Grid $grid)
    {
        $this->grid = $grid;
    }


    public function render()
    {
        $new = "新增一级代理";
        $url = $this->grid->getCreateUrl();
        $class = $this->grid->makeName('dialog-create');

        [$width, $height] = $this->grid->option('dialog_form_area');

        Form::dialog($new)
            ->click(".{$class}")
            ->success('Dcat.reload()')
            ->dimensions($width, $height);

        return "<div class='pull-right'><button data-url='$url' class='btn btn-primary {$class}'><i class='feather icon-plus'></i><span class='d-none d-sm-inline'>&nbsp; $new</span></button></div>";
    }
}

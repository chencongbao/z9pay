<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\User\UpdateAgent as UpdateAgentForm;

class UpdateAgent extends RowAction
{
    protected $title = '<i class="feather icon-anchor"></i> 更换代理';

    public function render()
    {
        $form = UpdateAgentForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}

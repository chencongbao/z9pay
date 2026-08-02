<?php

namespace App\Admin\Actions\Grid\UserAgent;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\UserAgent\Delete as DeleteForm;

class Delete extends RowAction
{
    protected $title = '<i class="feather icon-trash-2"></i> 删除代理';

    public function render()
    {
        $form = DeleteForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-agent-delete');
    }
}

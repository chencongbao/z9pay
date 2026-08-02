<?php

namespace App\Admin\Actions\Grid\AgentUser;

use Dcat\Admin\Form;
use Dcat\Admin\Admin;
use Dcat\Admin\Grid\RowAction;

class CreateNextAgent extends RowAction
{
    protected string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function title()
    {
        return '<i class="feather icon-plus"></i> ' . __('新增下级代理') . ' &nbsp;&nbsp;';
    }

    public function render()
    {
        [$width, $height] = $this->parent->option('dialog_form_area') ?: ['800px', '600px'];

        Form::dialog('新增下级代理')->click(".{$this->getElementClass()}")->dimensions($width, $height)->forceRefresh()->success('Dcat.reload()');

        $this->setHtmlAttribute(['data-url' => $this->url]);

        return parent::render();
    }

    public function makeSelector()
    {
        return 'create-next-agent';
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-agent-create');
    }
}

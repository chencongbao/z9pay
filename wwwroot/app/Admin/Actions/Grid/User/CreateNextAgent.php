<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Form;
use Dcat\Admin\Admin;
use Dcat\Admin\Grid\RowAction;

class CreateNextAgent extends RowAction
{
    private const TITLE = '新增下级代理';

    protected string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function title()
    {
        return '<i class="feather icon-plus"></i> ' . self::TITLE . ' &nbsp;&nbsp;';
    }

    public function render()
    {
        [$width, $height] = $this->parent->option('dialog_form_area');

        Form::dialog(self::TITLE)->click(".{$this->getElementClass()}")->dimensions($width, $height)->forceRefresh()->success('Dcat.reload()');

        $this->setHtmlAttribute(['data-url' => $this->url]);

        return parent::render();
    }

    public function makeSelector(): string
    {
        return 'create-next-agent';
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-agent-create');
    }
}

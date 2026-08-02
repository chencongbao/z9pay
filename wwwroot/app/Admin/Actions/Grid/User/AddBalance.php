<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\User\AddBalance as AddBalanceForm;

class AddBalance extends RowAction
{
    protected $title = '金主代理资金加项';

    private $agentId = 0;

    private string $permission = 'users-index';

    public function __construct($agentId = 0, string $permission = 'users-index')
    {
        $this->agentId = $agentId;
        $this->permission = $permission;
    }

    public function render()
    {
        $form = AddBalanceForm::make()->payload(['agent_id' => $this->agentId, 'permission' => $this->permission]);

        return Modal::make()->lg()->title($this->title)->body($form)->button('<button class="btn btn-primary">' . $this->title . '</button>');
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can($this->permission);
    }
}

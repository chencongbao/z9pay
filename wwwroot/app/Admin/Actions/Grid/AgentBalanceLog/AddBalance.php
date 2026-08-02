<?php

namespace App\Admin\Actions\Grid\AgentBalanceLog;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\AgentBalanceLog\AddBalance as AddBalanceForm;

class AddBalance extends RowAction
{
    protected $title = '代理资金加项';

    private $agent_id = 0;

    public function __construct($agent_id = 0)
    {
        $this->agent_id = $agent_id;
    }

    public function render()
    {
        $form = AddBalanceForm::make()->payload(['id' => $this->getKey(), 'agent_id' => $this->agent_id]);

        return Modal::make()->lg()->title($this->title)->body($form)->button('<button class="btn btn-primary">' . $this->title . '</button>');
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-agent-balance-log-add');
    }
}

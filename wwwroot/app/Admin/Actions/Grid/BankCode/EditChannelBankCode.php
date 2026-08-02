<?php

namespace App\Admin\Actions\Grid\BankCode;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\BankCode\EditChannelBankCodeForm;

class EditChannelBankCode extends RowAction
{
    protected $title = '<span class="btn btn-info">编辑</span>';

    private int $id = 0;

    public function __construct(int $id = 0)
    {
        $this->id = $id;
    }

    public function render()
    {
        $form = EditChannelBankCodeForm::make()->payload(['id' => $this->id]);

        return Modal::make()->lg()->title('编辑渠道代码')->body($form)->button($this->title);
    }
}

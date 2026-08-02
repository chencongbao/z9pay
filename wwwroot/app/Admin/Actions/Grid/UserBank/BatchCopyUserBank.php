<?php

namespace App\Admin\Actions\Grid\UserBank;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\BatchAction;
use App\Admin\Forms\UserBank\BatchCopyForm;

class BatchCopyUserBank extends BatchAction
{
    protected $title = '<button class="btn btn-primary">批量复制</button>';

    public function render()
    {
        $form = BatchCopyForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title('批量复制')->body($form)->onLoad($this->getModalScript())->button($this->title);
    }

    protected function getModalScript()
    {
        return <<<JS
var key = {$this->getSelectedKeysScript()}

$('#user-bank-ids').val(key);
JS;
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('user-bank-batch-copy');
    }
}

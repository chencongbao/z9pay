<?php

namespace App\Admin\Actions\Grid\MerchantPayment;

use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\BatchAction;
use App\Admin\Forms\MerchantPayment\BatchUpdateRateSettingForm;

class BatchUpdateRateSetting extends BatchAction
{
    protected $title = '<button class="btn btn-primary">费率批量设置</button>';

    public function render()
    {
        $form = BatchUpdateRateSettingForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title('批量设置')->body($form)->onLoad($this->getModalScript())->button($this->title);
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('merchant-payment-batch-rate');
    }

    protected function getModalScript()
    {
        return <<<JS
var key = {$this->getSelectedKeysScript()}

$('#merchant-payment-id').val(key);
JS;
    }
}

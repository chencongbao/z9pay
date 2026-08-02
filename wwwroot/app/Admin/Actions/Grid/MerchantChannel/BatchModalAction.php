<?php

namespace App\Admin\Actions\Grid\MerchantChannel;

use Dcat\Admin\Admin;
use Dcat\Admin\Grid\BatchAction;
use Dcat\Admin\Widgets\Modal;

abstract class BatchModalAction extends BatchAction
{
    protected $modalTitle;

    protected $formClass;

    protected $hiddenSelector = '#merchant-channel-id';

    protected $requireSelection = true;

    protected string $permission = 'merchant-channels';

    public function render()
    {
        $formClass = $this->formClass;
        $form = $formClass::make()->payload(['id' => $this->getKey()]);

        $modal = Modal::make()->lg()->title($this->modalTitle)->body($form)->button($this->title);

        if ($this->hiddenSelector) {
            $modal->onLoad($this->getModalScript());
        }

        return $modal;
    }

    public function actionScript()
    {
        if (!$this->requireSelection) {
            return;
        }

        $warning = __('请选择操作项!');

        return <<<JS
function (data, target, action) {
    var key = {$this->getSelectedKeysScript()}

    if (key.length === 0) {
        Dcat.error('{$warning}');
        return false;
    }
}
JS;
    }

    protected function getModalScript()
    {
        return <<<JS
var key = {$this->getSelectedKeysScript()}

$('{$this->hiddenSelector}').val(key);
JS;
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can($this->permission);
    }
}

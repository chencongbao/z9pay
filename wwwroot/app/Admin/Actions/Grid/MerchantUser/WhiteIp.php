<?php

namespace App\Admin\Actions\Grid\MerchantUser;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\MerchantUser\WhiteIpForm;

class WhiteIp extends RowAction
{
    protected $title = '<i class="feather icon-unlock"></i> 白名单设置';

    public function render()
    {
        $form = WhiteIpForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}

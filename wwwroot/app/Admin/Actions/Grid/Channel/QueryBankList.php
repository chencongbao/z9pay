<?php

namespace App\Admin\Actions\Grid\Channel;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Renderable\Channel\BankListJson;

class QueryBankList extends RowAction
{
    protected $title = '<i class="feather icon-list"></i> 获取渠道银行';

    public function render()
    {
        return Modal::make()->lg()->title('渠道银行 JSON')->body(BankListJson::make()->payload(['id' => $this->getKey()]))->button($this->title);
    }
}

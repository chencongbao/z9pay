<?php

namespace App\Admin\Actions\Grid\MerchantChannel;

class BatchOpenFloat extends BatchToggleAction
{
	protected $title = '<i class="feather icon-check"></i> 浮动一键开启';

    protected $field = 'float_status';

    protected $value = 1;

    protected $confirmText = '开启所选渠道浮动';

    protected $actionKey = 'merchant.channel.batch_open_float';

    protected $logText = '批量开启 商户渠道浮动';

    protected string $permission = 'merchant-channel-float-status';
}

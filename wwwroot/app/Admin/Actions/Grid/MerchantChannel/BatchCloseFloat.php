<?php

namespace App\Admin\Actions\Grid\MerchantChannel;

class BatchCloseFloat extends BatchToggleAction
{
	protected $title = '<i class="feather icon-x"></i> 浮动一键关闭';

    protected $field = 'float_status';

    protected $value = 0;

    protected $confirmText = '关闭所选渠道浮动';

    protected $actionKey = 'merchant.channel.batch_close_float';

    protected $logText = '批量关闭 商户渠道浮动';

    protected string $permission = 'merchant-channel-float-status';
}

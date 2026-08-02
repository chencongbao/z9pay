<?php

namespace App\Admin\Actions\Grid\MerchantChannel;

class BatchCloseChannel extends BatchToggleAction
{
	protected $title = '<i class="feather icon-x"></i> 状态一键关闭';

    protected $field = 'status';

    protected $value = 0;

    protected $confirmText = '关闭所选渠道状态';

    protected $actionKey = 'merchant.channel.batch_close';

    protected $logText = '批量关闭 商户渠道';

    protected string $permission = 'merchant-channel-status';
}

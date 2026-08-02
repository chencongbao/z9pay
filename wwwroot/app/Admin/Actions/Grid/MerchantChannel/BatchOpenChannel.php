<?php

namespace App\Admin\Actions\Grid\MerchantChannel;

class BatchOpenChannel extends BatchToggleAction
{
	protected $title = '<i class="feather icon-check"></i> 状态一键开启';

    protected $field = 'status';

    protected $value = 1;

    protected $confirmText = '开启所选渠道状态';

    protected $actionKey = 'merchant.channel.batch_open';

    protected $logText = '批量开启 商户渠道';

    protected string $permission = 'merchant-channel-status';
}

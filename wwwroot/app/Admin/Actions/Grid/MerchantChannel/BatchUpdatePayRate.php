<?php

namespace App\Admin\Actions\Grid\MerchantChannel;

use App\Admin\Forms\MerchantChannel\BatchUpdatePayRateForm;

class BatchUpdatePayRate extends BatchModalAction
{
	protected $title = '<button class="btn btn-primary">额外手续费修改</button>';

    protected $modalTitle = '额外手续费修改';

    protected $formClass = BatchUpdatePayRateForm::class;

    protected string $permission = 'merchant-channel-batch-rate';
}

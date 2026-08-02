<?php

namespace App\Admin\Actions\Grid\MerchantChannel;

use App\Admin\Forms\MerchantChannel\UpdatePayMinMaxAmountForm;

class UpdatePayMinMaxAmount extends BatchModalAction
{
	protected $title = '<button class="btn btn-primary">代收单笔限额批量修改</button>';

    protected $modalTitle = '代收单笔限额批量修改';

    protected $formClass = UpdatePayMinMaxAmountForm::class;

    protected string $permission = 'merchant-channel-batch-pay-limit';
}

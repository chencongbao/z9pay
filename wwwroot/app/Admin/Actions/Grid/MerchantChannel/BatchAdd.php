<?php

namespace App\Admin\Actions\Grid\MerchantChannel;

use App\Admin\Forms\MerchantChannel\BatchAddForm;

class BatchAdd extends BatchModalAction
{
	protected $title = '<button class="btn btn-primary">批量新增</button>';

    protected $modalTitle = '批量新增';

    protected $formClass = BatchAddForm::class;

    protected $hiddenSelector = null;

    protected $requireSelection = false;

    protected $modalSize = 'xl';

    protected string $permission = 'merchant-channel-batch-add';
}

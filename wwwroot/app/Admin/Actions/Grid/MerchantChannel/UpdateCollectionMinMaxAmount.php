<?php

namespace App\Admin\Actions\Grid\MerchantChannel;

use App\Admin\Forms\MerchantChannel\UpdateCollectionMinMaxAmountForm;

class UpdateCollectionMinMaxAmount extends BatchModalAction
{
	protected $title = '<button class="btn btn-primary">代付单笔限额批量修改</button>';

    protected $modalTitle = '代付单笔限额批量修改';

    protected $formClass = UpdateCollectionMinMaxAmountForm::class;

    protected string $permission = 'merchant-channel-batch-collection-limit';
}

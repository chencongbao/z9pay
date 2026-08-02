<?php

namespace App\Admin\Actions\Grid\User;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\User\CacheStats as CacheStatsForm;

class CacheStats extends RowAction
{
    protected $title = '<i class="fa fa-database"></i> 押金与统计';

    public function render()
    {
        $form = CacheStatsForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}

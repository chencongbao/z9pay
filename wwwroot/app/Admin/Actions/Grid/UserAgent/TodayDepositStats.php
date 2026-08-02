<?php

namespace App\Admin\Actions\Grid\UserAgent;

use Dcat\Admin\Widgets\Modal;
use Dcat\Admin\Grid\RowAction;
use App\Admin\Forms\UserAgent\TodayDepositStats as TodayDepositStatsForm;

class TodayDepositStats extends RowAction
{
    protected $title = '<i class="fa fa-bar-chart"></i> 今日统计';

    public function render()
    {
        $form = TodayDepositStatsForm::make()->payload(['id' => $this->getKey()]);

        return Modal::make()->lg()->title($this->title)->body($form)->button($this->title);
    }
}

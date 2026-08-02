<?php

namespace App\Admin\Metrics\Admin\UserBalanceLog;

use App\Admin\Metrics\Admin\AdminCard;
use App\Models\UserBalanceLog;
use App\Services\Common\ModelQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Card1 extends AdminCard
{


    function __construct($data = [], $begin_date = '', $end_date = '')
    {
        parent::__construct("交易总金额");
        $this->parameters = array_merge([
            'created_at' => ['start' => $begin_date, 'end' => $end_date]
        ], $data);
    }

    protected function init()
    {
        parent::init();
        $this->style("fa fa-money red-bg");
    }

    public function handle(Request $request)
    {
        $model = App::make(ModelQueryService::class)->excute(new UserBalanceLog(), ['is_agent' => 0]);
        $total_amount = bob_unit_format($model->sum('amount'));
        $this->content($total_amount);
    }
}

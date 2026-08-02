<?php

namespace App\Admin\Metrics\Admin\DepositOrder;

use App\Admin\Metrics\Admin\AdminCard;
use App\Models\DepositOrder;
use App\Services\Common\ModelQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Card1 extends AdminCard
{


    function __construct($data = [], $begin_date = '', $end_date = '')
    {
        parent::__construct("订单实付金额");
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
        $model = App::make(ModelQueryService::class)->excute(new DepositOrder(),['status'=>5]);
        $total_amount = bob_unit_format($model->sum('actual_amount'));
        $this->content($total_amount);
    }
}

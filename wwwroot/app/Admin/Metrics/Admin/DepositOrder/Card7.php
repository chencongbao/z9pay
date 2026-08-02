<?php

namespace App\Admin\Metrics\Admin\DepositOrder;

use App\Models\DepositOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Admin\Metrics\Admin\AdminCard;
use App\Services\Common\ModelQueryService;

class Card7 extends AdminCard
{
    function __construct($data = [], $begin_date = '', $end_date = '')
    {
        parent::__construct("金主代理总手续费");
        $this->parameters = array_merge([
            'created_at' => ['start' => $begin_date, 'end' => $end_date]
        ], $data);
    }

    protected function init()
    {
        parent::init();
        $this->style("fa fa-money bg-blue");
    }

    public function handle(Request $request)
    {
        $model = App::make(ModelQueryService::class)->excute(new DepositOrder(), ['status' => 5]);
        $total_fee = $model->sum(DB::raw('user_commission + user_agent1_commission + user_agent2_commission + user_agent3_commission + user_agent4_commission + user_agent5_commission'));
        $this->content(bob_unit_format($total_fee));
    }
}

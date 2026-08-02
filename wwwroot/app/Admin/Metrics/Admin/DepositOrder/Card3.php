<?php

namespace App\Admin\Metrics\Admin\DepositOrder;

use App\Models\DepositOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Admin\Metrics\Admin\AdminCard;
use App\Services\Common\ModelQueryService;

class Card3 extends AdminCard
{

    public $delay = 10;

    function __construct($data = [], $begin_date = '', $end_date = '')
    {
        parent::__construct("净交易额");
        $this->parameters = array_merge([
            'created_at' => ['start' => $begin_date, 'end' => $end_date]
        ], $data);
    }

    protected function init()
    {
        parent::init();
        $this->style("fa fa-money emerald-bg");
    }

    public function handle(Request $request)
    {
        $model = App::make(ModelQueryService::class)->excute(new DepositOrder(),['status'=>5]);
        $result = $model->select(
            DB::raw('SUM(actual_amount) as sum_actual_amount'),
            DB::raw('SUM(merchant_fee) as sum_merchant_fee'),
            DB::raw('SUM(merchant_extra_fee) as sum_merchant_extra_fee')
        )->first();
        $amount = bob_unit_format(bob_amount_format($result->sum_actual_amount)  - bob_amount_format($result->sum_merchant_fee) - bob_amount_format($result->sum_merchant_extra_fee));
        $this->content($amount);
    }
}

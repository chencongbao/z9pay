<?php

namespace App\Admin\Metrics\Admin\DepositOrder;

use App\Admin\Metrics\Admin\AdminCard;
use App\Models\DepositOrder;
use App\Services\Common\ModelQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Card6 extends AdminCard
{
    function __construct($data = [], $begin_date = '', $end_date = '')
    {
        parent::__construct("商户总额外手续费");
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
        $model = App::make(ModelQueryService::class)->excute(new DepositOrder(),['status'=>5]);
        $total_merchant_extra_fee = bob_unit_format($model->sum('merchant_extra_fee'));
        $this->content($total_merchant_extra_fee);
    }
}

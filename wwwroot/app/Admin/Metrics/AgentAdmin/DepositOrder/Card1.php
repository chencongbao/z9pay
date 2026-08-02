<?php

namespace App\Admin\Metrics\AgentAdmin\DepositOrder;

use App\Admin\Metrics\Admin\AdminCard;
use App\Models\DepositOrder;
use App\Services\Common\ModelQueryService;
use Dcat\Admin\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Card1 extends AdminCard
{


    function __construct($data = [], $begin_date = '', $end_date = '')
    {
        parent::__construct(admin_trans_label("order_actual_amount"));
        $this->parameters = array_merge([
            'begin_date' => $begin_date,
            'end_date' => $end_date
        ],$data);
    }

    protected function init()
    {
        parent::init();
        $this->style("fa fa-money red-bg");
    }

    public function handle(Request $request)
    {
        $model = App::make(ModelQueryService::class)->excute(new DepositOrder(),['status'=>5,'merchant_agent_id'=>Admin::user()->id]);
        $total_amount = bob_unit_format($model->sum('actual_amount'));
        $this->content($total_amount);
    }
}

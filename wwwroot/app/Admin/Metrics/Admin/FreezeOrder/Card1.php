<?php

namespace App\Admin\Metrics\Admin\FreezeOrder;

use App\Models\FreezeOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Admin\Metrics\Admin\AdminCard;
use App\Services\Common\ModelQueryService;

class Card1 extends AdminCard
{


    function __construct($data = [], $begin_date = '', $end_date = '')
    {
        parent::__construct("总冻结金额");
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
        $model = App::make(ModelQueryService::class)->excute(new FreezeOrder());
        $total_amount = bob_unit_format($model->sum('amount'));
        $this->content($total_amount);
    }
}

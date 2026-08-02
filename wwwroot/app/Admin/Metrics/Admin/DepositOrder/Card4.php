<?php

namespace App\Admin\Metrics\Admin\DepositOrder;

use App\Models\DepositOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Admin\Metrics\Admin\AdminCard;
use App\Services\Common\ModelQueryService;

class Card4 extends AdminCard
{
    function __construct($data = [], $begin_date = '', $end_date = '')
    {
        parent::__construct("成功率");
        $this->parameters = array_merge([
            'created_at' => ['start' => $begin_date, 'end' => $end_date]
        ], $data);
    }

    protected function init()
    {
        parent::init();
        $this->style("fa fa-percent bg-blue");
    }

    public function handle(Request $request)
    {
        $model = App::make(ModelQueryService::class)->excute(new DepositOrder());
        $result = $model->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) as successful_orders')
            ->selectRaw('ROUND(SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as success_rate')
            ->first();
        $this->content(floatval($result->success_rate)."%");
    }
}

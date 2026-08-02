<?php

namespace App\Admin\Metrics\Admin\MerchantUser;

use App\Admin\Metrics\Admin\AdminCard;
use App\Models\MerchantInfo;
use App\Services\Common\ModelQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Card3 extends AdminCard
{


    function __construct($data = [])
    {
        parent::__construct("总结算金额");
        $this->parameters = $data;
    }

    protected function init()
    {
        parent::init();
        $this->style("fa fa-money red-bg");
    }

    public function handle(Request $request)
    {
        $model = App::make(ModelQueryService::class)->excute(new MerchantInfo());
        $total_amount = $model->sum('settlement_amount');
        $this->content(bob_unit_format($total_amount));
    }
}

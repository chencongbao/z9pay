<?php

namespace App\Admin\Metrics\MerchantAdmin\DepositOrder;

use App\Models\DepositOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Admin\Metrics\Admin\AdminCard;
use App\Services\Common\ModelQueryService;

class Card3 extends AdminCard
{
    function __construct($data = [], $begin_date = '', $end_date = '')
    {
        parent::__construct(admin_trans_label("total_order_number"));
        $this->parameters = array_merge([
            'begin_date' => $begin_date,
            'end_date' => $end_date
        ],$data);
    }

    protected function init()
    {
        parent::init();
        $this->style("feather icon-shopping-cart green-bg");
    }

    public function handle(Request $request)
    {
        $model = App::make(ModelQueryService::class)->excute(new DepositOrder(),['mid'=>bob_merchant_user_pid()]);
        $total_number = intval($model->count());
        $this->content($total_number);

    }
}

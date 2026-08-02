<?php

namespace App\Admin\Metrics\MerchantAdmin\TransferOrder;

use App\Admin\Metrics\Admin\AdminCard;
use App\Models\TransferOrder;
use App\Services\Common\ModelQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Card4 extends AdminCard
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
        $model = App::make(ModelQueryService::class)->excute(new TransferOrder(),['type'=>0,'mid'=>bob_merchant_user_pid()]);
        $total = bob_unit_format($model->count());
        $this->content($total);
    }
}

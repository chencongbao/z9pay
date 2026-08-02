<?php

namespace App\Admin\Metrics\MerchantAdmin\SettlementOrder;

use App\Admin\Metrics\Admin\AdminCard;
use App\Models\TransferOrder;
use App\Services\Common\ModelQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Card3 extends AdminCard
{
    function __construct($data = [], $begin_date = '', $end_date = '')
    {
        parent::__construct(admin_trans_label("total_extral_fee"));
        $this->parameters = array_merge([
            'begin_date' => $begin_date,
            'end_date' => $end_date
        ],$data);
    }

    protected function init()
    {
        parent::init();
        $this->style("fa fa-money bg-blue");
    }

    public function handle(Request $request)
    {
        $model = App::make(ModelQueryService::class)->excute(new TransferOrder(),['status'=>4,'type'=>1,'mid'=>bob_merchant_user_pid()]);
        $total_merchant_extra_fee = bob_unit_format($model->sum('merchant_extra_fee'));
        $this->content($total_merchant_extra_fee);
    }
}

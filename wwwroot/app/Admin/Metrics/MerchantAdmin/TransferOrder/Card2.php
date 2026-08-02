<?php

namespace App\Admin\Metrics\MerchantAdmin\TransferOrder;

use App\Admin\Metrics\Admin\AdminCard;
use App\Models\TransferOrder;
use App\Services\Common\ModelQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Card2 extends AdminCard
{
    function __construct($data = [], $begin_date = '', $end_date = '')
    {
        parent::__construct(admin_trans_label("total_fee"));
        $this->parameters = array_merge([
            'begin_date' => $begin_date,
            'end_date' => $end_date
        ],$data);
    }

    protected function init()
    {
        parent::init();
        $this->style("fa fa-money emerald-bg");
    }

    public function handle(Request $request)
    {
        $model = App::make(ModelQueryService::class)->excute(new TransferOrder(),['status'=>4,'type'=>0,'mid'=>bob_merchant_user_pid()]);
        $total_amount = bob_unit_format($model->sum('merchant_fee'));
        $this->content($total_amount);
    }
}

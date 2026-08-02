<?php

namespace App\Admin\Metrics\AgentAdmin\TransferOrder;

use App\Admin\Metrics\Admin\AdminCard;
use App\Models\TransferOrder;
use App\Services\Common\ModelQueryService;
use Dcat\Admin\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Card2 extends AdminCard
{
    function __construct($data = [], $begin_date = '', $end_date = '')
    {
        parent::__construct(admin_trans_label("total_commision_fee"));
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
        $model = App::make(ModelQueryService::class)->excute(new TransferOrder(),['status'=>4,'type'=>0,'merchant_agent_id'=>Admin::user()->id]);
        $totalAmount = 0;
        $model->select('id',"merchant_agent1_id","merchant_agent1_commission","merchant_agent2_id","merchant_agent2_commission","merchant_agent3_id","merchant_agent3_commission")->chunkById(1000,function ($result)use(&$totalAmount){
            foreach ($result as $item) {
                if(Admin::user()->id == $item->merchant_agent1_id){
                    $totalAmount += $item->merchant_agent1_commission;
                }
                if(Admin::user()->id == $item->merchant_agent2_id){
                    $totalAmount += $item->merchant_agent2_commission;
                }
                if(Admin::user()->id == $item->merchant_agent3_id){
                    $totalAmount += $item->merchant_agent3_commission;
                }
            }
        });
        $this->content(bob_amount_format($totalAmount));
    }
}

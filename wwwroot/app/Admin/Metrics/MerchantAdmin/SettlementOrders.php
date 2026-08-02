<?php

namespace App\Admin\Metrics\MerchantAdmin;

use App\Models\TransferOrder;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Metrics\Round;
use Illuminate\Http\Request;

class SettlementOrders extends Round
{

    public $colors = ["#7367f0","#f012be","#3085d6","#21b978","#ea5455","#0d0f11"];

    protected function init()
    {
        parent::init();
        $this->title('昨日结算订单统计');
        $this->chartLabels(array_values(config('default.transfer_status')));
        $this->chartColors($this->colors);
    }

    /**
     * 处理请求
     *
     * @param Request $request
     *
     * @return mixed|void
     */
    public function handle(Request $request)
    {
        $data1 = [];
        $data2 = [];
        foreach(config('default.transfer_status') as $k=>$v){
            $count = TransferOrder::where('mid',bob_merchant_user_pid())->where('type',1)->where('status',$k)->count();
            $data1[] = [
                'url' => Admin::app()->getRoute('settlement-orders.index',['status'=>$k]),
                'status' => $count,
                'name' => $v,
                'color' => $this->colors[$k-1] ?? ''
            ];
            $data2[] = $count;
        }
        $this->withContent($data1);
        $this->withChart($data2);
    }

    /**
     * 设置图表数据.
     *
     * @param array $data
     *
     * @return $this
     */
    public function withChart(array $data)
    {
        return $this->chart([
            'series' => $data,
            'chart' => [
                'width' => 350,
                'type' => 'pie'
            ],
            'responsive' => [
                [
                    'breakpoint' => [
                        'options' => [
                            'chart' => [
                                'width' => 200
                            ],
                            'legend' => [
                                'position' => 'bottom'
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * 卡片内容.
     *
     * @param int $finished
     * @param int $pending
     * @param int $rejected
     *
     * @return $this
     */
    public function withContent($data = [])
    {

        $html = '<div class="col-12 d-flex flex-column flex-wrap text-center" style="max-width: 220px">';
        foreach($data as $k=>$v){
            $html .= '<div class="chart-info d-flex justify-content-between mb-1" >
          <div class="series-info d-flex align-items-center">
              <i class="fa fa-circle-o text-bold-700 text-primary" style="color:'.$v['color'].' !important;"></i>
              <a class="text-bold-600 ml-50" href="'.$v['url'].'" >'.$v['name'].'</a>
          </div>
          <div class="product-result">
              <span>'.$v['status'].'</span>
          </div>
    </div>';
        }
        $html .= '</div>';

        return $this->content($html);
    }
}

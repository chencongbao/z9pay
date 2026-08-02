<?php

namespace App\Admin\Metrics\MerchantAdmin;

use App\Models\ReportMerchant;
use App\Models\TransferOrder;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\ApexCharts\Chart;
use Dcat\Admin\Widgets\Metrics\Round;
use Illuminate\Http\Request;

class TransferOrders extends Round
{

    public $colors = ["#7367f0","#f012be","#3085d6","#21b978","#ea5455","#0d0f11"];

    protected function init()
    {
        parent::init();
        $this->title(admin_trans_label("yestoday_transfer_count_title"));
        $this->chartLabels(array_values($this->getStatusArray()));
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
        $data = $this->getYestodayData();
        foreach($this->getStatusArray() as $k=>$v){
            $data1[] = [
                'url' => Admin::app()->getRoute('transfer-orders.index', $this->buildOrderFilterParams((int)$k)),
                'status' => $data[$k],
                'name' => $v,
                'color' => $this->colors[$k-1] ?? ''
            ];
            $data2[] = $data[$k];
        }
        $this->withContent($data1);
        $this->withChart($data2);
    }

    public function useChart()
    {
        $data = $this->getYestodayData();
        if($data[0] > 0){
            return $this->chart ?: ($this->chart = Chart::make());
        }
        return;
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

        $html = '<div class="col-12 d-flex flex-column flex-wrap text-center">';
        foreach($data as $k=>$v){
            $html .= '<div class="chart-info d-flex justify-content-start mb-1" >
          <div class="series-info d-flex align-items-center" style="padding-right: 20px">
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

    private function getStatusArray()
    {
        return collect(config('default.transfer_status'))->transform(function ($item,$key) {
            return admin_trans_option($key,"transfer_status") ?: $item;
        })->filter(function ($item,$key){
            return in_array($key,[4,5]);
        })->toArray();
    }

    private function buildOrderFilterParams(int $status): array
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        return [
            'status' => $status,
            'created_at' => [
                'start' => $yesterday . ' 00:00:00',
                'end' => $yesterday . ' 23:59:59',
            ],
        ];
    }

    private function getYestodayData()
    {
        $data = [
            0 => 0,
            4 => 0 ,
            5 => 0,
        ];
        $result = ReportMerchant::where('mid',bob_merchant_user_pid())->where('date_add',date('Y-m-d',strtotime("-1 day")))->first(['transfer_order_number_total','transfer_order_number_success','transfer_order_number_fail']);
        if($result){
            $data[0] = $result->transfer_order_number_total;
            $data[4] = $result->transfer_order_number_success;
            $data[5] = $result->transfer_order_number_fail;
        }
        return $data;
    }
}

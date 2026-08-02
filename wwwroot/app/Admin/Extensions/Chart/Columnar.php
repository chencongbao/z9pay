<?php

namespace App\Admin\Extensions\Chart;

use App\Models\DepositOrder;
use Dcat\Admin\Widgets\ApexCharts\Chart;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Columnar extends Chart
{
    public function __construct($containerSelector = null, $options = [])
    {
        parent::__construct($containerSelector, $options);

        $this->setUpOptions();
    }

    protected function setUpOptions(): void
    {
        $this->options([
            'chart' => ['height' => 350, 'type' => 'bar'],
            'plotOptions' => ['bar' => ['horizontal' => false, 'columnWidth' => '55%']],
            'dataLabels' => ['enabled' => false],
            'stroke' => ['show' => true, 'width' => 2, 'colors' => ['transparent']],
            'yaxis' => ['title' => ['text' => '$ (thousands)']],
            'fill' => ['opacity' => 1],
            'title' => ['text' => '充值总订单数时段分布', 'align' => 'center'],
            'xaxis' => ['categories' => []],
        ]);

        $this->option('series', [
            ['name' => '全部订单', 'data' => $this->hourlyData()],
            ['name' => '成功', 'data' => $this->hourlyData(5)],
            ['name' => '失败', 'data' => $this->hourlyData(6)],
            ['name' => '超时', 'data' => $this->hourlyData(4)],
            ['name' => '刷单', 'data' => $this->hourlyData()],
        ]);
        $this->option('xaxis', [
            'categories' => ['00-01', '01-02', '02-03', '03-04', '04-05', '05-06', '06-07', '07-08', '08-09', '09-10', '10-11', '11-12', '12-13', '13-14', '14-15', '15-16', '16-17', '17-18', '18-19', '19-20', '20-21', '21-22', '22-23', '23-00'],
        ]);
    }

    private function hourlyData(?int $status = null): array
    {
        $cacheKey = 'admin:deposit_order_columnar:' . md5(json_encode([
            'mid' => request('mid'),
            'channel_id' => request('channel_id'),
            'user_id' => request('user_id'),
            'begin_date' => request('begin_date'),
            'end_date' => request('end_date'),
            'status' => $status,
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($status) {
            $query = DepositOrder::query();
            if (request('mid')) {
                $query->where('mid', request('mid'));
            }
            if (request('channel_id')) {
                $query->where('channel_id', request('channel_id'));
            }
            if (request('user_id')) {
                $query->where('user_id', request('user_id'));
            }
            if (request('begin_date') && request('end_date')) {
                $query->where('created_at', '>=', request('begin_date') . ' 00:00:00')->where('created_at', '<=', request('end_date') . ' 23:59:59');
            }
            if ($status !== null) {
                $query->where('status', $status);
            }

            $result = $query->groupBy('hour')->get(['hour', DB::raw('count(*) as total')])->keyBy('hour');
            $data = [];
            for ($hour = 0; $hour < 24; $hour++) {
                $data[] = intval(optional($result->get($hour))->total);
            }

            return $data;
        });
    }
}

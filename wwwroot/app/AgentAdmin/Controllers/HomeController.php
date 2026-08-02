<?php

namespace App\AgentAdmin\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Layout\Column;
use Dcat\Admin\Layout\Content;
use App\Models\ReportMerchantAgent;
use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    public function index(Content $content): Content
    {
        $agent = Admin::user();
        $report = ReportMerchantAgent::where('aid', $agent->id)->where('date_add', date('Y-m-d', strtotime('-1 day')))->first([
            'deposit_order_total_amount',
            'transfer_order_total_amount',
        ]);

        return $content->header(__('menu.titles.index'))->body(function (Row $row) use ($agent, $report) {
            $row->column(12, function (Column $column) use ($agent, $report) {
                $column->row(view('agent-admin.home.tongji', [
                    'balance_amount' => $agent->balance_amount,
                    'deposit_order_total_amount' => data_get($report, 'deposit_order_total_amount', 0),
                    'transfer_order_total_amount' => data_get($report, 'transfer_order_total_amount', 0),
                ]));
            });
        });
    }
}

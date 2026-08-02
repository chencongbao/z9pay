<?php
namespace App\MerchantAdmin\Renderable\BankCode;

use App\Services\MerchantAdmin\MerchantExportFileService;
use Dcat\Admin\Admin;
use Dcat\Admin\Support\LazyRenderable;
use Dcat\Admin\Widgets\Table;

class HistoryExportData extends LazyRenderable
{
    public function render()
    {
        $header = [admin_trans_label("history_export_date"), admin_trans_label("history_export_action")];
        $data = app(MerchantExportFileService::class)->historyRows('merchant_bank_codes', (int)Admin::user()->id);
        $table = new Table($header, $data);
        $table->withBorder();
        $table->setStyle(['custom-data-table data-table table-bordered complex-headers']);
        return $table;
    }
}

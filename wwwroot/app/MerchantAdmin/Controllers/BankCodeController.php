<?php

namespace App\MerchantAdmin\Controllers;

use Dcat\Admin\Grid;
use App\Models\BankCode;
use App\Admin\Controllers\CommonController;
use App\MerchantAdmin\Actions\BankCode\ExportData;
use App\Services\Cache\Merchant\CacheMerchantBaseInfoService;

class BankCodeController extends CommonController
{
    protected $disableCreate = true;

    protected $disableEdit = true;

    public function title(): string
    {
        return '银行代码';
    }

    protected function grid(): Grid
    {
        return Grid::make(new BankCode(), function (Grid $grid) {
            $merchant = app(CacheMerchantBaseInfoService::class)->excute(bob_merchant_user_pid());
            $currencyId = (int) data_get($merchant, 'currency_id', 0);

            $grid->model()->where('currency_id', $currencyId);
            $grid->model()->setConstraints(['currency_id' => $currencyId]);

            $grid->column('code');
            $grid->column('name');
            $grid->disableActions();
            $grid->disableCreateButton();

            $grid->tools(function (Grid\Tools $tools) {
                $tools->append(new ExportData());
            });

            $grid->filter(function (Grid\Filter $filter) {
                $filter->expand();
                $filter->panel();
                $filter->like('code')->width(3);
                $filter->like('name')->width(3);
            });
        });
    }
}

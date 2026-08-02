<?php

namespace App\Jobs;

use Carbon\Carbon;
use Vtiful\Kernel\Excel;
use Illuminate\Bus\Queueable;
use App\Models\MerchantBalanceLog;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Order\OrderCacheService;
use App\Services\Common\ModelQueryService;
use App\Services\MerchantAdmin\MerchantExportFileService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Cache\CacheConstPrefixService;

class MerchantMerchantBalanceLogDataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 2000;

    public $tries = 1;

    public $timeout = 1000;

    public $data = [];

    public $block = 1;

    public $cache_key;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data = [])
    {
        $this->data = $data;
        $this->cache_key = CacheConstPrefixService::MERCHANT_MERCHANT_BALANCE_LOG_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        App::setLocale($this->data['locale']);
        set_time_limit(0);
        ini_set('memory_limit', '3072m');
        $exportType = 'merchant_merchant_balance_logs';
        $exportFileService = App::make(MerchantExportFileService::class);
        $exportFileService->ensureDirectory($exportType, (int)$this->data['admin_id']);
        $config = ['path' => $exportFileService->absoluteDirectory($exportType, (int)$this->data['admin_id'])];
        $excel = new Excel($config);

        $name = date("YmdHis") . '-merchant_balance_logs.xlsx';
        $url = $exportFileService->downloadUrl($exportType, $name, $this->data['download_base_url'] ?? null);
        $type = "merchant_merchant_balance_logs";
        event(new \App\Events\SystemMerchantExportEvent(["block" => 0, 'url' => $url,'status'=>0,'type'=>$type,"admin_id"=>$this->data['admin_id']]));

        $fileObject = $excel->fileName($name);
        $fileObject = $fileObject->header(['ID', admin_trans_label("ordernumber"), admin_trans_label("order_no"), admin_trans_field("type"), admin_trans_field("amount"), admin_trans_field("merchant_fee"), admin_trans_field("currency"), admin_trans_field("balance_amount"), admin_trans_field("remark"), admin_trans_label("created_at"),admin_trans_field("usdt_rate"),admin_trans_field("usdt_amount"),admin_trans_field("usdt_balance_amount")]);

        $model = App::make(ModelQueryService::class)->excute(new MerchantBalanceLog(), $this->data);
        $model->select("id", "mid", 'type', "type_id", "amount", "currency_id", "fee", "remark", "balance_amount", "admin_id",'created_at','usdt_rate','usdt_amount','usdt_balance_amount')->chunkById(self::CHUNK_SIZE, function ($result) use ($fileObject,$url,$type) {
            $data = [];
            foreach ($result as $item) {

                $ordernumber = "";
                $order_no = "";

                if(($item->type == 1 || $item->type == 9 || $item->type == 10) && $item->type_id > 0){
                    $depositOrder = App::make(OrderCacheService::class)->getDepositById($item->type_id);
                    $ordernumber = optional($depositOrder)->offsetGet('ordernumber');
                    $order_no = optional($depositOrder)->offsetGet('order_no');
                }
                if(in_array($item->type,[2,3,4,5,6,7,8,13]) && $item->type_id > 0){
                    $transferOrder = App::make(OrderCacheService::class)->getTransferById($item->type_id);
                    $ordernumber = optional($transferOrder)->offsetGet('ordernumber');
                    $order_no = optional($transferOrder)->offsetGet('order_no');
                }



                $data[] = [
                    $item->id,
                    $ordernumber,
                    $order_no,
                    admin_trans_option($item->type,"merchant_balance_type"),
                    floatval($item->amount),
                    floatval($item->fee),
                    optional(collect(config('default.currency'))->firstWhere('id', $item->currency_id))->offsetGet('name'),
                    floatval($item->balance_amount),
                    $item->remark,
                    Carbon::parse($item->created_at)->format("Y-m-d H:i:s"),
                    floatval($item->usdt_rate),
                    floatval($item->usdt_amount),
                    floatval($item->usdt_balance_amount),
                ];
            }
            $fileObject->data($data);
            event(new \App\Events\SystemMerchantExportEvent(["block" => $this->block, 'url' => $url,'status'=>1,'type'=>$type,"admin_id"=>$this->data['admin_id']]));
            $this->block += 1;
        });
        $fileObject->output();
        event(new \App\Events\SystemMerchantExportEvent(["block" => $this->block, 'url' => $url,'status'=>2,'type'=>$type,"admin_id"=>$this->data['admin_id']]));
        Cache::delete($this->cache_key);
    }

    public function failed(\Throwable $exception)
    {
        Cache::delete($this->cache_key);
    }
}

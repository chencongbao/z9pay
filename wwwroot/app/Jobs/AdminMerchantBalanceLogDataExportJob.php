<?php

namespace App\Jobs;

use Carbon\Carbon;
use Vtiful\Kernel\Excel;
use Illuminate\Bus\Queueable;
use App\Models\MerchantBalanceLog;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Order\OrderCacheService;
use App\Services\Common\ModelQueryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Cache\CacheConstPrefixService;

class AdminMerchantBalanceLogDataExportJob implements ShouldQueue
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
        $this->cache_key = CacheConstPrefixService::ADMIN_MERCHANT_BALANCE_LOG_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        set_time_limit(0);
        ini_set('memory_limit', '3072m');
        $export_path = 'export/admin_merchant_balance_logs/'.$this->data['admin_id'];
        if (!Storage::exists("public/".$export_path)) {
            Storage::makeDirectory("public/".$export_path);
        }
        $config = ['path' => storage_path("app/public/".$export_path)];
        $excel = new Excel($config);

        $name = date("YmdHis") . '-merchant_balance_logs.xlsx';
        $url = $this->data['url']."/".$export_path."/" . $name;
        $type = "admin_merchant_balance_logs";
        event(new \App\Events\SystemGloabelExportEvent(["block" => 0, 'url' => $url,'status'=>0,'type'=>$type,"admin_id"=>$this->data['admin_id']]));

        $fileObject = $excel->fileName($name);
        $fileObject = $fileObject->header(['ID', '交易单号', '商户', '交易类型', '币种', '交易金额', '手续费', '账户余额', "备注", "操作人","交易时间","USDT汇率","USDT金额","USDT账户余额"]);

        $model = App::make(ModelQueryService::class)->excute(new MerchantBalanceLog(), $this->data);
        $model->select("id", "mid", 'type', "type_id", "amount", "currency_id", "fee", "remark", "balance_amount", "admin_id",'created_at','usdt_rate','usdt_amount','usdt_balance_amount')->with(['merchant_info' => function ($query) {
            $query->select('merchant_user_id', "name");
        }, 'admin_user' => function ($query) {
            $query->select("id", "name");
        }])->chunkById(self::CHUNK_SIZE, function ($result) use ($fileObject,$url,$type) {
            $data = [];
            foreach ($result as $item) {

                $ordernumber = "";

                if(($item->type == 1 || $item->type == 9 || $item->type == 10) && $item->type_id > 0){
                    $ordernumber = optional(App::make(OrderCacheService::class)->getDepositById($item->type_id))->offsetGet('ordernumber');
                }
                if(in_array($item->type,[2,3,4,5,6,7,8,13]) && $item->type_id > 0){
                    $ordernumber = optional(App::make(OrderCacheService::class)->getTransferById($item->type_id))->offsetGet('ordernumber');
                }

                $data[] = [
                    $item->id,
                    $ordernumber,
                    optional($item->merchant_info)->offsetGet('name'),
                    config('default.merchant_balance_type')[$item->type] ?? '',
                    optional(collect(config('default.currency'))->firstWhere('id', $item->currency_id))->offsetGet('name'),
                    floatval($item->amount),
                    floatval($item->fee),
                    floatval($item->balance_amount),
                    $item->remark,
                    optional($item->admin_user)->offsetGet('name'),
                    Carbon::parse($item->created_at)->format("Y-m-d H:i:s"),
                    floatval($item->usdt_rate),
                    floatval($item->usdt_amount),
                    floatval($item->usdt_balance_amount),
                ];
            }
            $fileObject->data($data);
            event(new \App\Events\SystemGloabelExportEvent(["block" => $this->block, 'url' => $url,'status'=>1,'type'=>$type,"admin_id"=>$this->data['admin_id']]));
            $this->block += 1;
        });
        $fileObject->output();
        event(new \App\Events\SystemGloabelExportEvent(["block" => $this->block, 'url' => $url,'status'=>2,'type'=>$type,"admin_id"=>$this->data['admin_id']]));
        Cache::delete($this->cache_key);
    }

    public function failed(\Throwable $exception)
    {
        Cache::delete($this->cache_key);
    }
}

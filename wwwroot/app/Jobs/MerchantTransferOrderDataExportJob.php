<?php

namespace App\Jobs;

use App\Models\BankCode;
use App\Models\TransferOrder;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Common\ModelQueryService;
use App\Services\MerchantAdmin\MerchantExportFileService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Vtiful\Kernel\Excel;

class MerchantTransferOrderDataExportJob implements ShouldQueue
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
        $this->cache_key = CacheConstPrefixService::MERCHANT_TRANSFER_ORDER_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
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
        $exportType = 'merchant_transfer_orders';
        $exportFileService = App::make(MerchantExportFileService::class);
        $exportFileService->ensureDirectory($exportType, (int)$this->data['admin_id']);
        $config = ['path' => $exportFileService->absoluteDirectory($exportType, (int)$this->data['admin_id'])];
        $excel = new Excel($config);

        $name = date("YmdHis") . '-transfer_orders.xlsx';
        $url = $exportFileService->downloadUrl($exportType, $name, $this->data['download_base_url'] ?? null);
        $type = "merchant_transfer_orders";
        event(new \App\Events\SystemMerchantExportEvent(["block" => 0, 'url' => $url,'status'=>0,'type'=>$type,"admin_id"=>$this->data['admin_id']]));

        $fileObject = $excel->fileName($name);
        $fileObject = $fileObject->header(array_values(['id' => 'ID', 'merchant_name' => admin_trans_label("merchant"), 'order_no' => admin_trans_label("order_no"), 'ordernumber' => admin_trans_label("ordernumber"), 'amount' => admin_trans_field("amount"), 'actual_amount' => admin_trans_field("actual_amount"), 'currency' => admin_trans_field("currency"), 'merchant_fee' => admin_trans_field("merchant_fee"),'merchant_extra_fee'=>admin_trans_field("merchant_extra_fee"), 'status' => admin_trans_label("order_status"), 'created_at' => admin_trans_label("created_at"), 'success_time' => admin_trans_label("success_time"),'bank_name' => admin_trans_label("bank_name"),'holder_name'=>admin_trans_label("bank_holder_name"),'card_no' => admin_trans_label("bank_card_no")]));
        $this->data['type'] = 0;
        $model = App::make(ModelQueryService::class)->excute(new TransferOrder(), $this->data);
        $model->select("id", "mid",'order_no', 'ordernumber',"currency_id", "amount", "actual_amount", "merchant_fee", "merchant_extra_fee",'merchant_rate','channel_id','user_id','status','created_at','success_time','bank_name','holder_name','card_no','bank_id')->with(['merchant_info' => function ($query) {
            $query->select('merchant_user_id', "name");
        }])->chunkById(self::CHUNK_SIZE, function ($result) use ($fileObject,$url,$type) {
            $data = [];
            foreach ($result as $item) {
                $data[] = [
                    $item->id,
                    optional($item->merchant_info)->offsetGet('name'),
                    $item->order_no,
                    $item->ordernumber,
                    floatval($item->amount),
                    floatval($item->actual_amount),
                    optional(collect(config('default.currency'))->firstWhere('id', $item->currency_id))->offsetGet('name'),
                    floatval($item->merchant_fee),
                    floatval($item->merchant_extra_fee),
                    admin_trans_option($item->status,"transfer_status"),
                    Carbon::parse($item->created_at)->format("Y-m-d H:i:s"),
                    $item->success_time > 0 ? date('Y-m-d H:i:s', $item->success_time) : '',
                    empty($item->bank_name) ? optional(BankCode::where('id', $item->bank_id)->where('currency_id',$item->currency_id)->first())->offsetGet('name') : $item->bank_name,
                    $item->holder_name,
                    $item->card_no,
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

<?php

namespace App\Jobs;

use App\Models\DepositOrder;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Common\GetPaymentDetailService;
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

class MerchantDepositOrderDataExportJob implements ShouldQueue
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
        $this->cache_key = CacheConstPrefixService::MERCHANT_DEPOSIT_ORDER_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
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
        $exportType = 'merchant_deposit_order';
        $exportFileService = App::make(MerchantExportFileService::class);
        $exportFileService->ensureDirectory($exportType, (int)$this->data['admin_id']);
        $config = ['path' => $exportFileService->absoluteDirectory($exportType, (int)$this->data['admin_id'])];
        $excel = new Excel($config);

        $name = date("YmdHis") . '-deposit-order.xlsx';
        $url = $exportFileService->downloadUrl($exportType, $name, $this->data['download_base_url'] ?? null);
        $type = "merchant_deposit_export";

        event(new \App\Events\SystemMerchantExportEvent(["block" => 0, 'url' => $url,'status'=>0,'type'=>$type,"admin_id"=>$this->data['admin_id']]));

        $fileObject = $excel->fileName($name);
        $fileObject = $fileObject->header(array_values(['id' => 'ID','merchant'=>admin_trans_label("merchant"), 'order_no' => admin_trans_label("order_no"), 'ordernumber' => admin_trans_label("ordernumber"),'amount'=>admin_trans_field("amount"),'pay_amount'=>admin_trans_field("pay_amount"),'actual_amount'=>admin_trans_field("actual_amount"),'currency' => admin_trans_field("currency"),'merchant_fee'=>admin_trans_field("merchant_fee"),'merchant_extra_fee'=>admin_trans_field("merchant_extra_fee"),'status'=>admin_trans_label("order_status"),'created_at'=>admin_trans_label("created_at"),'success_time'=>admin_trans_label("success_time"),'payment_name'=>admin_trans_label("payment_type"),'account_amount'=>admin_trans_field("account_amount"),'usdt_rate'=>admin_trans_field("usdt_rate"),'usdt_account_amount'=>admin_trans_field("usdt_account_amount")]));

        $model = App::make(ModelQueryService::class)->excute(new DepositOrder(), $this->data);
        $model->select("id", "mid", 'order_no', "ordernumber", "amount", "pay_amount", "actual_amount", "currency_id", "merchant_fee", "merchant_extra_fee", "merchant_rate", "channel_id", "user_id", "payment_id", 'status', "created_at", "success_time","pay_name",'usdt_rate')->with(['merchant_info'=>function ($query) {
            $query->select("merchant_user_id","name");
        }])->chunkById(self::CHUNK_SIZE, function ($result) use ($fileObject,$url,$type) {
            $data = [];
            foreach ($result as $item) {
                $data[] = [
                    $item->id,
                    optional($item->merchant_info)->offsetGet('name'),
                    $item->order_no,
                    $item->ordernumber,
                    $item->amount,
                    $item->pay_amount,
                    $item->actual_amount,
                    optional(collect(config('default.currency'))->firstWhere('id',$item->currency_id))->offsetGet('name'),
                    $item->merchant_fee,
                    $item->merchant_extra_fee,
                    admin_trans_option($item->status,"deposit_status"),
                    Carbon::parse($item->created_at)->format('Y-m-d H:i:s'),
                    $item->success_time > 0 ? date('Y-m-d H:i:s', $item->success_time) : '',
                    App::make(GetPaymentDetailService::class)->excute($item->payment_id),
                    $item->actual_amount > 0 ? ($item->actual_amount - $item->merchant_fee - $item->merchant_extra_fee) : 0,
                    $item->usdt_rate,
                    $item->usdt_rate > 0 ? bcdiv((string)($item->actual_amount - $item->merchant_fee - $item->merchant_extra_fee),(string)$item->usdt_rate,2) : 0,
                ];
            }
            $fileObject->data($data);
            event(new \App\Events\SystemMerchantExportEvent(["block" => $this->block, 'url' => $url,'status'=>1,'type'=>$type,"admin_id"=>$this->data['admin_id']]));
            $this->block += 1;
        });
        $fileObject->output();
        event(new \App\Events\SystemMerchantExportEvent(["block" => $this->block, 'url' => $url,'status'=>2,'type'=>$type,"admin_id"=>$this->data['admin_id']]));
        Cache::delete( $this->cache_key);
    }

    public function failed(\Throwable $exception)
    {
        Cache::delete( $this->cache_key);
    }
}

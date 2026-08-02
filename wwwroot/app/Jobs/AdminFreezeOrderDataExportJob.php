<?php

namespace App\Jobs;

use App\Models\BankCode;
use App\Models\FreezeOrder;
use App\Models\TransferOrder;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Common\ModelQueryService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Vtiful\Kernel\Excel;

class AdminFreezeOrderDataExportJob implements ShouldQueue
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
        $this->cache_key = CacheConstPrefixService::ADMIN_FREEZE_ORDER_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
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
        $export_path = 'export/admin_freeze_orders/'.$this->data['admin_id'];
        if (!Storage::exists("public/".$export_path)) {
            Storage::makeDirectory("public/".$export_path);
        }
        $config = ['path' => storage_path("app/public/".$export_path)];
        $excel = new Excel($config);

        $name = date("YmdHis") . '-freeze_orders.xlsx';
        $url = $this->data['url']."/".$export_path."/" . $name;
        $type = "admin_freeze_orders";
        event(new \App\Events\SystemGloabelExportEvent(["block" => 0, 'url' => $url,'status'=>0,'type'=>$type,"admin_id"=>$this->data['admin_id']]));

        $fileObject = $excel->fileName($name);
        $fileObject = $fileObject->header(["ID","商户","币种","商户订单号","平台订单号","冻结状态","冻结金额","金主","冻结时间","解冻时间","备注"]);
        $ordernumber = $this->data['freeze_ordernumber'] ?? $this->data['ordernumber'] ?? "";
        $order_no = $this->data['freeze_order_no'] ?? $this->data['order_no'] ?? "";
        unset($this->data['freeze_ordernumber'], $this->data['freeze_order_no'], $this->data['ordernumber'], $this->data['order_no']);
        $model = App::make(ModelQueryService::class)->excute(new FreezeOrder(), $this->data);
        if(!empty($ordernumber)){
            $model = $model->whereHas('deposit_order',function ($query)use($ordernumber){
                $query->where('ordernumber',$ordernumber);
            });
        }
        if(!empty($order_no)){
            $model = $model->whereHas('deposit_order',function ($query)use($order_no){
                $query->where('order_no',$order_no);
            });
        }
        $model->select("id", "mid",'deposit_order_id', 'user_id',"amount", "unfreeze_time", "remark", "created_at", "status")->with(['merchant_info' => function ($query) {
            $query->select('merchant_user_id', "name");
        }, 'user' => function ($query) {
            $query->select("id", "name");
        },'deposit_order' => function ($query) {
            $query->select("id", "ordernumber","currency_id","order_no","amount");
        }])->chunkById(self::CHUNK_SIZE, function ($result) use ($fileObject,$url,$type) {
            $data = [];
            foreach ($result as $item) {
                $data[] = [
                    $item->id,
                    optional($item->merchant_info)->offsetGet('name'),
                    optional(collect(config('default.currency'))->firstWhere('id', optional($item->deposit_order)->offsetGet('currency_id')))->offsetGet('name'),
                    optional($item->deposit_order)->offsetGet('order_no'),
                    optional($item->deposit_order)->offsetGet('ordernumber'),
                    config('default.freeze_status')[$item->status] ?? '',
                    floatval($item->amount),
                    optional($item->user)->offsetGet('name'),
                    Carbon::parse($item->created_at)->format("Y-m-d H:i:s"),
                    $item->unfreeze_time > 0 ? Carbon::createFromTimestamp((int)$item->unfreeze_time)->format("Y-m-d H:i:s") : "",
                    $item->remark
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

<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\BankCode;
use Vtiful\Kernel\Excel;
use App\Models\TransferOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Common\ModelQueryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Cache\CacheConstPrefixService;
use App\Services\Cache\MerchantAgent\GetMerchantAgentListService;

class AdminTransferOrderDataExportJob implements ShouldQueue
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
        $this->cache_key = CacheConstPrefixService::ADMIN_TRANSFER_ORDER_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
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
        $export_path = 'export/admin_transfer_orders/'.$this->data['admin_id'];
        if (!Storage::exists("public/".$export_path)) {
            Storage::makeDirectory("public/".$export_path);
        }
        $config = ['path' => storage_path("app/public/".$export_path)];
        $excel = new Excel($config);

        $name = date("YmdHis") . '-transfer_orders.xlsx';
        $url = $this->data['url']."/".$export_path."/" . $name;
        $type = "admin_transfer_orders";
        event(new \App\Events\SystemGloabelExportEvent(["block" => 0, 'url' => $url,'status'=>0,'type'=>$type,"admin_id"=>$this->data['admin_id']]));

        $fileObject = $excel->fileName($name);
        $fileObject = $fileObject->header(array_values(['id' => 'ID','mid' => '商户编号', 'merchant_info.name' => '商户名称', 'order_no' => '商户单号', 'ordernumber' => '平台单号', 'channel_ordernumber'=>'渠道单号','currency' => '币种', 'amount' => '订单金额', 'actual_amount' => '实付金额', 'merchant_fee' => "商户手续费",'merchant_extra_fee' => "商户额外手续费", 'merchant_rate' => '费率', 'merchant_agent1_name' => '一级代理', 'merchant_agent2_name' => '二级代理', 'merchant_agent3_name' => '三级代理', 'merchant_agent1_rate' => '一级代理费率', 'merchant_agent2_rate' => '二级代理费率', 'merchant_agent3_rate' => '三级代理费率', 'channel_name' => '三方','user_name'=>'金主', 'status' => '状态', 'created_at' => '创建时间', 'success_time' => '成功时间','bank_name' => "银行名称",'holder_name'=>'银行卡户名','card_no' => '银行卡卡号','callback_time'=>'回调时间','remark'=>"备注"]));
        $merchantAgentMap = collect(App::make(GetMerchantAgentListService::class)->excute())->keyBy('id');
        $this->data['type'] = 0;
        $model = App::make(ModelQueryService::class)->excute(new TransferOrder(), $this->data);
        $model->select("id", "mid",'order_no', 'ordernumber',"currency_id", "amount", "actual_amount", "merchant_fee", "merchant_extra_fee",'merchant_rate','merchant_agent1_id','merchant_agent2_id','merchant_agent3_id','merchant_agent1_rate','merchant_agent2_rate','merchant_agent3_rate','channel_id','user_id','status','created_at','success_time','bank_name','holder_name','card_no','bank_id','callback_time','channel_ordernumber','remark')->with(['merchant_info' => function ($query) {
            $query->select('merchant_user_id', "name");
        }, 'user' => function ($query) {
            $query->select("id", "name");
        },'channel' => function ($query) {
            $query->select("id", "name");
        }])->chunkById(self::CHUNK_SIZE, function ($result) use ($fileObject,$url,$type,$merchantAgentMap) {
            $data = [];
            foreach ($result as $item) {
                $merchantAgentNames = $this->merchantAgentNames($item, $merchantAgentMap);
                $data[] = [
                    $item->id,
                    $item->mid,
                    optional($item->merchant_info)->offsetGet('name'),
                    $item->order_no,
                    $item->ordernumber,
                    $item->channel_ordernumber,
                    optional(collect(config('default.currency'))->firstWhere('id', $item->currency_id))->offsetGet('name'),
                    floatval($item->amount),
                    floatval($item->actual_amount),
                    floatval($item->merchant_fee),
                    floatval($item->merchant_extra_fee),
                    floatval($item->merchant_rate),
                    $merchantAgentNames[0],
                    $merchantAgentNames[1],
                    $merchantAgentNames[2],
                    $item->merchant_agent1_rate,
                    $item->merchant_agent2_rate,
                    $item->merchant_agent3_rate,
                    optional($item->channel)->offsetGet('name'),
                    optional($item->user)->offsetGet('name'),
                    config('default.transfer_status')[$item->status] ?? '',
                    Carbon::parse($item->created_at)->format("Y-m-d H:i:s"),
                    $item->success_time > 0 ? date('Y-m-d H:i:s', $item->success_time) : '',
                    empty($item->bank_name) ? optional(BankCode::where('id', $item->bank_id)->where('currency_id',$item->currency_id)->first())->offsetGet('name') : $item->bank_name,
                    $item->holder_name,
                    $item->card_no,
                    $item->callback_time > 0 ? date('Y-m-d H:i:s', $item->callback_time) : '',
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

    private function merchantAgentNames($item, $merchantAgentMap): array
    {
        $agentIds = [
            (int)$item->merchant_agent1_id,
            (int)$item->merchant_agent2_id,
            (int)$item->merchant_agent3_id,
        ];

        $agentNames = [];
        foreach ($agentIds as $agentId) {
            $agentNames[] = $agentId > 0 ? data_get($merchantAgentMap->get($agentId), 'bname', "【#{$agentId}】") : '';
        }

        return $agentNames;
    }
}

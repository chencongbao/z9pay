<?php

namespace App\Jobs;

use Carbon\Carbon;
use Vtiful\Kernel\Excel;
use Illuminate\Bus\Queueable;
use App\Models\UserBalanceLog;
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

class AdminUserAgentBalanceLogDataExportJob implements ShouldQueue
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
        $this->cache_key = CacheConstPrefixService::ADMIN_USER_AGENT_BALANCE_LOGS_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
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
        $export_path = 'export/admin_user_agent_balance_logs/'.$this->data['admin_id'];
        if (!Storage::exists("public/".$export_path)) {
            Storage::makeDirectory("public/".$export_path);
        }
        $config = ['path' => storage_path("app/public/".$export_path)];
        $excel = new Excel($config);

        $name = date("YmdHis") . '-user-agent-balance-logs.xlsx';
        $url = $this->data['url']."/".$export_path."/" . $name;
        $type = "admin_user_agent_balance_logs";
        event(new \App\Events\SystemGloabelExportEvent(["block" => 0, 'url' => $url,'status'=>0,'type'=>$type,"admin_id"=>$this->data['admin_id']]));

        $fileObject = $excel->fileName($name);
        $fileObject = $fileObject->header(['ID', '交易单号', '交易类型', '交易金额', '账户余额', '所属代理', '交易时间', '备注']);

        $model = App::make(ModelQueryService::class)->excute(new UserBalanceLog(), $this->data)->where('is_agent', 1);
        $model->select("id", "mid", 'user_id', "action_user_id", "ordernumber", "amount", "type", "type_id", "remark", "balance_amount", "created_at", "is_agent", "type_balance_amount", "order_type")->with(['user' => function ($query) {
            $query->select("id", "name");
        }])->chunkById(self::CHUNK_SIZE, function ($result) use ($fileObject,$url,$type) {
            $data = [];
            foreach ($result as $item) {
                $ordernumber = (string) $item->ordernumber;
                if($ordernumber === '' && $item->type == 1 && $item->type_id > 0){
                    $deposit_order = App::make(OrderCacheService::class)->getDepositById($item->type_id);
                    if($deposit_order){
                        $ordernumber = optional($deposit_order)->offsetGet('ordernumber');
                    }
                }
                if($ordernumber === '' && $item->type == 2 && $item->type_id > 0){
                    $transfer_order = App::make(OrderCacheService::class)->getTransferById($item->type_id);
                    if($transfer_order){
                        $ordernumber = optional($transfer_order)->offsetGet('ordernumber');
                    }
                }
                $data[] = [
                    $item->id,
                    $ordernumber,
                    config('default.agent_balance_type')[$item->type] ?? '',
                    $item->amount,
                    $item->balance_amount,
                    optional($item->user)->offsetGet('name'),
                    Carbon::parse($item->created_at)->format('Y-m-d H:i:s'),
                    $item->remark,
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

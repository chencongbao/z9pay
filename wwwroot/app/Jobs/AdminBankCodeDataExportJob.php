<?php

namespace App\Jobs;

use App\Models\BankCode;
use App\Models\DepositOrder;
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

class AdminBankCodeDataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 2000;

    public $tries = 1;

    public $timeout = 1000;

    public $data = [];

    public $block = 0;

    public $cache_key;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data = [])
    {
        $this->data = $data;
        $this->cache_key = CacheConstPrefixService::ADMIN_BANK_CODE_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
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
        $export_path = 'export/admin_bank_codes/'.$this->data['admin_id'];
        if (!Storage::exists("public/".$export_path)) {
            Storage::makeDirectory("public/".$export_path);
        }
        $config = ['path' => storage_path("app/public/".$export_path)];
        $excel = new Excel($config);

        $name = date("YmdHis") . '-bank_codes.xlsx';
        $url = $this->data['url']."/".$export_path."/" . $name;
        $type = "admin_bank_codes";
        event(new \App\Events\SystemGloabelExportEvent(["block" => 0, 'url' => $url,'status'=>0,'type'=>$type,"admin_id"=>$this->data['admin_id']]));

        $fileObject = $excel->fileName($name);
        $fileObject = $fileObject->header(['ID', __('bank-code.fields.name'),__('bank-code.fields.code')]);

        $model = App::make(ModelQueryService::class)->excute(new BankCode(), $this->data);
        $model->select("id", "name", 'code')->chunkById(self::CHUNK_SIZE, function ($result) use ($fileObject,$url,$type) {
            $data = [];
            foreach ($result as $item) {
                $data[] = [
                    $item->id,
                    $item->name,
                    $item->code
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

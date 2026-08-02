<?php

namespace App\Jobs;

use App\Models\BankCode;
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

class MerchantBankCodeDataExportJob implements ShouldQueue
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
        $this->cache_key = CacheConstPrefixService::MERCHANT_BANK_CODE_EXPORT_HAS_EXIST . ($this->data['admin_id'] ?? 0);
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
        $exportType = 'merchant_bank_codes';
        $exportFileService = App::make(MerchantExportFileService::class);
        $exportFileService->ensureDirectory($exportType, (int)$this->data['admin_id']);
        $config = ['path' => $exportFileService->absoluteDirectory($exportType, (int)$this->data['admin_id'])];
        $excel = new Excel($config);

        $name = date("YmdHis") . '-bank_codes.xlsx';
        $url = $exportFileService->downloadUrl($exportType, $name, $this->data['download_base_url'] ?? null);
        $type = "merchant_bank_codes";
        event(new \App\Events\SystemMerchantExportEvent(["block" => 0, 'url' => $url,'status'=>0,'type'=>$type,"admin_id"=>$this->data['admin_id']]));

        $fileObject = $excel->fileName($name);
        $fileObject = $fileObject->header(['ID', __('bank-code.fields.name'),__('bank-code.fields.code')]);

        $code = $this->data['code'] ?? null;
        $nameFilter = $this->data['name'] ?? null;
        unset($this->data['code'], $this->data['name']);

        $model = App::make(ModelQueryService::class)->excute(new BankCode(), $this->data);
        if ($code !== null && $code !== '') {
            $model = $model->where('code', 'like', "%{$code}%");
        }
        if ($nameFilter !== null && $nameFilter !== '') {
            $model = $model->where('name', 'like', "%{$nameFilter}%");
        }
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

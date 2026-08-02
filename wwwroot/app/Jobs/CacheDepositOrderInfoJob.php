<?php

namespace App\Jobs;

use App\Services\Cache\DepositOrder\CacheDepositOrderInfoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class CacheDepositOrderInfoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $ordernumber;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($ordernumber)
    {
        $this->ordernumber = $ordernumber;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        App::make(CacheDepositOrderInfoService::class)->excute($this->ordernumber, 0, true);
    }
}

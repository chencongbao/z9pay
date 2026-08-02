<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\Order\OrderCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CacheTransferOrderInfoJob implements ShouldQueue
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
        App::make(OrderCacheService::class)->refreshTransferByOrdernumber($this->ordernumber);
    }
}

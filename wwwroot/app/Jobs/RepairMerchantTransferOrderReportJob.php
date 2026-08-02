<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\Report\ReportPendingDateService;

class RepairMerchantTransferOrderReportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $uniqueFor = 1800;

    public $mid;

    public $date;

    public function __construct($mid, $date)
    {
        $this->mid = $mid;
        $this->date = $date;
    }

    public function uniqueId(): string
    {
        return (string) $this->date;
    }

    public function handle(): void
    {
        App::make(ReportPendingDateService::class)->addDates([(string)$this->date]);
    }
}

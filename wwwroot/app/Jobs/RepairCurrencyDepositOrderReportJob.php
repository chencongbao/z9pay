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

class RepairCurrencyDepositOrderReportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 1000;

    public $uniqueFor = 1800;

    public $currency_id;

    public $date;

    public function __construct($currency_id, $date)
    {
        $this->currency_id = (int) $currency_id;
        $this->date = (string) $date;
    }

    public function uniqueId(): string
    {
        return $this->date;
    }

    public function handle(): void
    {
        App::make(ReportPendingDateService::class)->addDates([(string)$this->date]);
    }
}

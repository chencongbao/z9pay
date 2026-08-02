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

class RepairMerchantAgentTransferOrderReportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 1000;

    public $uniqueFor = 1800;

    public $agent_id;

    public $date;

    public function __construct($agent_id = 0, $date = '')
    {
        $this->agent_id = $agent_id;
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

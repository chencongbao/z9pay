<?php

namespace App\Jobs\Report;

use Illuminate\Support\Facades\App;
use App\Services\Report\ReportPendingDateService;

class HandleReportUserAgentJob extends AbstractReportDateJob
{
    public function handle(): void
    {
        App::make(ReportPendingDateService::class)->addDates([$this->date_add]);
        $this->markReportJobDone();
    }

    protected function serviceMethod(): string
    {
        return 'buildUserAgents';
    }
}

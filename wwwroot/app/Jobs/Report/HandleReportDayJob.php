<?php

namespace App\Jobs\Report;

class HandleReportDayJob extends AbstractReportDateJob
{
    protected function serviceMethod(): string
    {
        return 'buildDays';
    }
}

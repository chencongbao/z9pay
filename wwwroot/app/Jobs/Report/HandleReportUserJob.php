<?php

namespace App\Jobs\Report;

class HandleReportUserJob extends AbstractReportDateJob
{
    protected function serviceMethod(): string
    {
        return 'buildUsers';
    }
}

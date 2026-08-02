<?php

namespace App\Jobs\Report;

class HandleReportAllJob extends AbstractReportDateJob
{
    protected function serviceMethod(): string
    {
        return 'excute';
    }
}

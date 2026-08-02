<?php

namespace App\Jobs\Report;

class HandleReportMerchantJob extends AbstractReportDateJob
{
    protected function serviceMethod(): string
    {
        return 'buildMerchants';
    }
}

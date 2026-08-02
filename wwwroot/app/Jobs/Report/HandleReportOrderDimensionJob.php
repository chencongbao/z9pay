<?php

namespace App\Jobs\Report;

class HandleReportOrderDimensionJob extends AbstractReportDateJob
{
    protected function serviceMethod(): string
    {
        return 'buildOrderDimensions';
    }
}

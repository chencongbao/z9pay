<?php

namespace App\Jobs\Report;

class HandleReportMerchantAgentJob extends AbstractReportDateJob
{
    protected function serviceMethod(): string
    {
        return 'buildMerchantAgents';
    }
}

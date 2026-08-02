<?php

namespace App\Jobs\Middleware;

use Illuminate\Support\Facades\Redis;

class RateJobLimited
{
    public $key = "rate_job_limited";

    public $limit = 1;

    function __construct($key = null, $limit = 1)
    {
        if ($key) $this->key = $key;
        $this->limit = $limit;
    }


    public function handle($job, $next)
    {
        Redis::funnel($this->key)->limit($this->limit)->then(function () use ($job, $next) {
            $next($job);
        }, function () use ($job) {
            $job->release(120);
        });
    }

}

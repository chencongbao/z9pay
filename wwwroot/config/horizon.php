<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:default' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 1,
        'pending' => 1,
        'completed' => 1,
        'recent_failed' => 1,
        'failed' => 1,
        'monitored' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 1,
            'queue' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 1024,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [],

    'environments' => [
        'production' => [
            'supervisor-callback' => [
                'tries' => 1,
                'connection' => 'redis',
                'queue' => ["callback","callback_low"],
                'balance' => 'auto',
                'maxProcesses' => env("QUEUE_MAX_PROCESSES_CALLBACK",1),
                'balanceMaxShift' => 3,
                'balanceCooldown' => 1,
                'timeout' => 120,
                'maxTime' => 3600,
                'maxJobs' => 2000,
            ],
            'supervisor-slow' => [
                'tries' => 1,
                'connection' => 'redis',
                'queue' => ['query','notice','export', 'count'],
                'balance' => 'auto',
                'maxProcesses' => env("QUEUE_MAX_PROCESSES_SLOW",1),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'timeout' => 1800,
                'maxTime' => 3600,
                'maxJobs' => 1000,
            ],
            'supervisor-transfer' => [
                'tries' => 1,
                'connection' => 'redis',
                'queue' => ['transfer','query','notice'],
                'balance' => 'auto',
                'maxProcesses' => env("QUEUE_MAX_PROCESSES_TRANSFER",1),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'timeout' => 120,
                'maxTime' => 3600,
                'maxJobs' => 1000,
            ]
        ],

        'local' => [
            'supervisor-callback' => [
                'tries' => 1,
                'connection' => 'redis',
                'queue' => ["callback","callback_low"],
                'balance' => 'auto',
                'maxProcesses' => env("QUEUE_MAX_PROCESSES_CALLBACK",1),
                'balanceMaxShift' => 3,
                'balanceCooldown' => 3,
                'timeout' => 120,
                'maxTime' => 3600,
                'maxJobs' => 2000,
            ],
            'supervisor-slow' => [
                'tries' => 1,
                'connection' => 'redis',
                'queue' => ['query','notice','export', 'count'],
                'balance' => 'simple',
                'maxProcesses' => env("QUEUE_MAX_PROCESSES_SLOW",1),
                'timeout' => 1800,
                'maxTime' => 3600,
                'maxJobs' => 1000,
            ],
            'supervisor-transfer' => [
                'tries' => 1,
                'connection' => 'redis',
                'queue' => ['transfer','query','notice','status'],
                'balance' => 'auto',
                'maxProcesses' => env("QUEUE_MAX_PROCESSES_TRANSFER",1),
                'balanceMaxShift' => 3,
                'balanceCooldown' => 3,
                'timeout' => 120,
                'maxTime' => 3600,
                'maxJobs' => 1000,
            ]
        ],
    ],
];

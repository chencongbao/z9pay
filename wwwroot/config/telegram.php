<?php

use App\Telegram\Commands\BdtCommand;
use App\Telegram\Commands\BrlCommand;
use App\Telegram\Commands\ChannelCommand;
use App\Telegram\Commands\ChannelFixedRateCommand;
use App\Telegram\Commands\ChannelRateCommand;
use App\Telegram\Commands\CnyCommand;
use App\Telegram\Commands\HelpCommand;
use App\Telegram\Commands\HkCommand;
use App\Telegram\Commands\IdrCommand;
use App\Telegram\Commands\InrCommand;
use App\Telegram\Commands\JpyCommand;
use App\Telegram\Commands\KrwCommand;
use App\Telegram\Commands\LakCommand;
use App\Telegram\Commands\MmkCommand;
use App\Telegram\Commands\MxnCommand;
use App\Telegram\Commands\MyrCommand;
use App\Telegram\Commands\NgnCommand;
use App\Telegram\Commands\NprCommand;
use App\Telegram\Commands\PhpCommand;
use App\Telegram\Commands\PkrCommand;
use App\Telegram\Commands\RubCommand;
use App\Telegram\Commands\ThbCommand;
use App\Telegram\Commands\TryCommand;
use App\Telegram\Commands\VndCommand;
return [
    /*
    |--------------------------------------------------------------------------
    | Your Telegram Bots
    |--------------------------------------------------------------------------
    | You may use multiple bots at once using the manager class. Each bot
    | that you own should be configured here.
    |
    | Here are each of the telegram bots config parameters.
    |
    | Supported Params:
    |
    | - name: The *personal* name you would like to refer to your bot as.
    |
    |       - token:    Your Telegram Bot's Access Token.
                        Refer for more details: https://core.telegram.org/bots#botfather
    |                   Example: (string) '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11'.
    |
    |       - commands: (Optional) Commands to register for this bot,
    |                   Supported Values: "Command Group Name", "Shared Command Name", "Full Path to Class".
    |                   Default: Registers Global Commands.
    |                   Example: (array) [
    |                       'admin', // Command Group Name.
    |                       'status', // Shared Command Name.
    |                       Acme\Project\Commands\BotFather\HelloCommand::class,
    |                       Acme\Project\Commands\BotFather\ByeCommand::class,
    |             ]
    */
    'bots' => [
        'mybot' => [
            'token' => env('TELEGRAM_BOT_TOKEN', '7106446770:AAHRR7cwRJtMooTbXYlZIr8ScdwEA410JRw'),
            'certificate_path' => env('TELEGRAM_CERTIFICATE_PATH', ''),
            /*
             * @see https://core.telegram.org/bots/api#update
             */
            'allowed_updates' => null,
            'commands' => [
                //Acme\Project\Commands\MyTelegramBot\BotCommand::class
            ],
        ],

        //        'mySecondBot' => [
        //            'token' => '123456:abc',
        //        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Bot Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the bots you wish to use as
    | your default bot for regular use.
    |
    */
    'default' => 'mybot',

    /*
    |--------------------------------------------------------------------------
    | Asynchronous Requests [Optional]
    |--------------------------------------------------------------------------
    |
    | When set to True, All the requests would be made non-blocking (Async).
    |
    | Default: false
    | Possible Values: (Boolean) "true" OR "false"
    |
    */
    'async_requests' => env('TELEGRAM_ASYNC_REQUESTS', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Handler [Optional]
    |--------------------------------------------------------------------------
    |
    | If you'd like to use a custom HTTP Client Handler.
    | Should be an instance of \Telegram\Bot\HttpClients\HttpClientInterface
    |
    | Default: GuzzlePHP
    |
    */
    'http_client_handler' => null,

    /*
    |--------------------------------------------------------------------------
    | Base Bot Url [Optional]
    |--------------------------------------------------------------------------
    |
    | If you'd like to use a custom Base Bot Url.
    | Should be a local bot api endpoint or a proxy to the telegram api endpoint
    |
    | Default: https://api.telegram.org/bot
    |
    */
    'base_bot_url' => env('TELEGRAM_BASE_BOT_URL', 'https://api.telegram.org/bot'),
    "proxy_key" => "UKsBH4VeGaoHHqtmEwtAAQKsBZ0evRjzETjcTXoGaXjIXbvXZHi45sYKq7T1FaY2",
    'send_rate' => [
        'global_max_attempts' => env('TELEGRAM_SEND_RATE_GLOBAL_MAX_ATTEMPTS', 1),
        'global_decay_seconds' => env('TELEGRAM_SEND_RATE_GLOBAL_DECAY_SECONDS', 1),
        'chat_max_attempts' => env('TELEGRAM_SEND_RATE_CHAT_MAX_ATTEMPTS', 1),
        'chat_decay_seconds' => env('TELEGRAM_SEND_RATE_CHAT_DECAY_SECONDS', 3),
        'retry_until_hours' => env('TELEGRAM_SEND_RATE_RETRY_UNTIL_HOURS', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resolve Injected Dependencies in commands [Optional]
    |--------------------------------------------------------------------------
    |
    | Using Laravel's IoC container, we can easily type hint dependencies in
    | our command's constructor and have them automatically resolved for us.
    |
    | Default: true
    | Possible Values: (Boolean) "true" OR "false"
    |
    */
    'resolve_command_dependencies' => true,

    /*
    |--------------------------------------------------------------------------
    | Register Telegram Global Commands [Optional]
    |--------------------------------------------------------------------------
    |
    | If you'd like to use the SDK's built in command handler system,
    | You can register all the global commands here.
    |
    | Global commands will apply to all the bots in system and are always active.
    |
    | The command class should extend the \Telegram\Bot\Commands\Command class.
    |
    | Default: The SDK registers, a help command which when a user sends /help
    | will respond with a list of available commands and description.
    |
    */
    'commands' => [
        HelpCommand::class,
        ChannelCommand::class,
        CnyCommand::class,
        VndCommand::class,
        InrCommand::class,
        IdrCommand::class,
        PhpCommand::class,
        ThbCommand::class,
        MyrCommand::class,
        BdtCommand::class,
        PkrCommand::class,
        TryCommand::class,
        BrlCommand::class,
        HkCommand::class,
        MxnCommand::class,
        MmkCommand::class,
        JpyCommand::class,
        NprCommand::class,
        KrwCommand::class,
        RubCommand::class,
        NgnCommand::class,
        LakCommand::class,
        ChannelRateCommand::class,
        ChannelFixedRateCommand::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Command Groups [Optional]
    |--------------------------------------------------------------------------
    |
    | You can organize a set of commands into groups which can later,
    | be re-used across all your bots.
    |
    | You can create 4 types of groups:
    | 1. Group using full path to command classes.
    | 2. Group using shared commands: Provide the key name of the shared command
    | and the system will automatically resolve to the appropriate command.
    | 3. Group using other groups of commands: You can create a group which uses other
    | groups of commands to bundle them into one group.
    | 4. You can create a group with a combination of 1, 2 and 3 all together in one group.
    |
    | Examples shown below are by the group type for you to understand each of them.
    */
    'command_groups' => [
        /* // Group Type: 1
           'commmon' => [
                Acme\Project\Commands\TodoCommand::class,
                Acme\Project\Commands\TaskCommand::class,
           ],
        */

        /* // Group Type: 2
           'subscription' => [
                'start', // Shared Command Name.
                'stop', // Shared Command Name.
           ],
        */

        /* // Group Type: 3
            'auth' => [
                Acme\Project\Commands\LoginCommand::class,
                Acme\Project\Commands\SomeCommand::class,
            ],

            'stats' => [
                Acme\Project\Commands\UserStatsCommand::class,
                Acme\Project\Commands\SubscriberStatsCommand::class,
                Acme\Project\Commands\ReportsCommand::class,
            ],

            'admin' => [
                'auth', // Command Group Name.
                'stats' // Command Group Name.
            ],
        */

        /* // Group Type: 4
           'myBot' => [
                'admin', // Command Group Name.
                'subscription', // Command Group Name.
                'status', // Shared Command Name.
                'Acme\Project\Commands\BotCommand' // Full Path to Command Class.
           ],
        */
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared Commands [Optional]
    |--------------------------------------------------------------------------
    |
    | Shared commands let you register commands that can be shared between,
    | one or more bots across the project.
    |
    | This will help you prevent from having to register same set of commands,
    | for each bot over and over again and make it easier to maintain them.
    |
    | Shared commands are not active by default, You need to use the key name to register them,
    | individually in a group of commands or in bot commands.
    | Think of this as a central storage, to register, reuse and maintain them across all bots.
    |
    */
    'shared_commands' => [
        // 'start' => Acme\Project\Commands\StartCommand::class,
        // 'stop' => Acme\Project\Commands\StopCommand::class,
        // 'status' => Acme\Project\Commands\StatusCommand::class,
    ],
];

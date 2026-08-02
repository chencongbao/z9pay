<?php

namespace App\Services\Telegram;

use Telegram\Bot\Api;
use GuzzleHttp\Client;
use App\Traits\HttpTrait;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

class TelegramInstanceService
{
    use HttpTrait;

    public function excute($debug = false, bool $withCommands = false, ?string $telegramBotToken = null)
    {
        $options = [
            'verify' => false,
            'headers' => [
                'X-TG-Proxy-Key' => config('telegram.proxy_key'),
            ],
        ];

        if ($debug) {
            $options['handler'] = $this->stack();
        }

        Api::setContainer(app());

        $telegram = new Api(
            $telegramBotToken ?: (intval(bob_admin_setting("telegram_turn_on")) == 0 ? '7106446770:AAHRR7cwRJtMooTbXYlZIr8ScdwEA410JRw' : bob_admin_setting("telegram_bot_token")),
            false,
            new GuzzleHttpClient(new Client($options)),
            config('telegram.base_bot_url')
        );

        if ($withCommands) {
            $telegram->addCommands(config('telegram.commands', []));
        }

        return $telegram;
    }
}

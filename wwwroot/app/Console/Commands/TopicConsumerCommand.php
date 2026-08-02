<?php

namespace App\Console\Commands;

use App\Jobs\HandleTronDatajob;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;
use Throwable;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class TopicConsumerCommand extends Command
{
    protected $signature = 'app:topic-consumer';

    protected $description = '订阅波场交易信息';

    public function handle(): int
    {
        try {
            $consumer = Kafka::createConsumer([config('kafka.topics')])
                ->withAutoCommit()
                ->withHandler(function (KafkaConsumerMessage $message) {
                    $body = $message->getBody();
                    if (empty($body)) {
                        return;
                    }

                    $data = json_decode($body, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || empty($data)) {
                        logger()->warning('波场Kafka消息解析失败', ['body' => $body, 'json_error' => json_last_error_msg()]);
                        return;
                    }

                    dispatch(new HandleTronDatajob($data))->onQueue('query');
                })
                ->build();

            $consumer->consume();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('波场Kafka消费异常：' . $e->getMessage());

            return self::FAILURE;
        }
    }
}

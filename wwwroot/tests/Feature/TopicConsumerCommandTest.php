<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\HandleTronDatajob;
use Junges\Kafka\Facades\Kafka;
use Illuminate\Support\Facades\Queue;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class TopicConsumerCommandTest extends TestCase
{
    public function test_empty_and_invalid_messages_do_not_dispatch_jobs(): void
    {
        Queue::fake();
        Kafka::fake()->shouldReceiveMessages([
            $this->message(''),
            $this->message('not-json'),
            $this->message('[]'),
            $this->message('"scalar"'),
        ]);

        $this->artisan('app:topic-consumer')->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_valid_non_empty_array_dispatches_tron_job_to_query_queue_with_same_payload(): void
    {
        Queue::fake();
        $payload = ['txID' => 'codex-tron', 'amount' => 12.34];
        Kafka::fake()->shouldReceiveMessages($this->message(json_encode($payload, JSON_UNESCAPED_UNICODE)));

        $this->artisan('app:topic-consumer')->assertExitCode(0);

        Queue::assertPushedOn('query', HandleTronDatajob::class);
        Queue::assertPushed(HandleTronDatajob::class, fn ($job) => $job->data === $payload);
    }

    public function test_consumer_build_or_consume_exception_returns_failure(): void
    {
        Queue::fake();
        Kafka::shouldReceive('createConsumer')->andThrow(new \RuntimeException('kafka unavailable'));

        $this->artisan('app:topic-consumer')
            ->expectsOutput('波场Kafka消费异常：kafka unavailable')
            ->assertExitCode(1);

        Queue::assertNothingPushed();
    }

    private function message($body): KafkaConsumerMessage
    {
        return new class($body) implements KafkaConsumerMessage {
            public function __construct(private $body)
            {
            }

            public function getBody()
            {
                return $this->body;
            }

            public function getKey(): mixed
            {
                return null;
            }

            public function getTopicName(): ?string
            {
                return 'codex-topic';
            }

            public function getPartition(): ?int
            {
                return 0;
            }

            public function getHeaders(): ?array
            {
                return [];
            }

            public function getOffset(): ?int
            {
                return 0;
            }

            public function getTimestamp(): ?int
            {
                return time();
            }
        };
    }
}

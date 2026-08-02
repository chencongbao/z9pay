<?php


namespace App\Extendtions\Logger;

use Monolog\DateTimeImmutable;
use Monolog\Logger;

class NewLogger extends Logger
{
    public function addRecord(int $level, string $message, array $context = [], DateTimeImmutable $datetime = null): bool
    {
        $offset = 0;
        $record = null;

        foreach ($this->handlers as $handler) {
            if (null === $record) {
                // skip creating the record as long as no handler is going to handle it
                if (!$handler->isHandling(['level' => $level])) {
                    continue;
                }

                $levelName = static::getLevelName($level);

                $record = [
                    'message' => $message,
                    'context' => $context,
                    'level' => $level,
                    'level_name' => $levelName,
                    'channel' => $this->name,
                    'datetime' => date('Y-m-d H:i:s'),
                    'extra' => [],
                ];

                try {
                    foreach ($this->processors as $processor) {
                        $record = $processor($record);
                    }
                } catch (Throwable $e) {
                    $this->handleException($e, $record);

                    return true;
                }
            }

            // once the record exists, send it to all handlers as long as the bubbling chain is not interrupted
            try {
                if (true === $handler->handle($record)) {
                    break;
                }
            } catch (Throwable $e) {
                $this->handleException($e, $record);

                return true;
            }
        }

        return null !== $record;
    }
}

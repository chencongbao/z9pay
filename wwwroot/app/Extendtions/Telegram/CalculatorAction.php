<?php

namespace App\Extendtions\Telegram;

use Throwable;
use App\Traits\TelegramTrait;
use App\Extendtions\Math\MathEvaluator;

class CalculatorAction
{
    use TelegramTrait;

    public $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [])
    {
        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '' || is_numeric(bob_replacement_empty($text)) || !$this->isMathExpression($text)) {
            return;
        }

        try {
            $result = (new MathEvaluator($text, null))->evaluate();
            if (is_numeric($result)) {
                $this->replyResult($message, $result);
            }
        } catch (Throwable $e) {
        }
    }

    private function replyResult(array $message, $result): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $message['chat']['id'] ?? 0,
            'text' => '计算结果：<code><b>' . bob_amount_format($result, 3) . '</b></code>',
            'parse_mode' => 'html',
            'reply_to_message_id' => $message['message_id'] ?? 0,
        ]);
    }
}

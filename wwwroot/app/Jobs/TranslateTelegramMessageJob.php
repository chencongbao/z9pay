<?php

namespace App\Jobs;

use Throwable;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\SystemNotice\SystemNoticeService;
use App\Services\Telegram\TelegramInstanceService;

class TranslateTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $message;

    public string $lang = "zh-CN";

    public string $text = "";

    public function __construct($message = [], $lang = "zh-CN", $text = "")
    {
        $this->message = $message;
        $this->lang = (string) $lang;
        $this->text = (string) $text;
    }

    public function handle(): void
    {
        try {
            $sourceText = (string)($this->text ?? '');
            if (trim($sourceText) === '') {
                return;
            }

            $result = $this->translate($sourceText, $this->lang);
            if (empty($result['translated_text']) || trim($result['translated_text']) === trim($sourceText)) {
                return;
            }
            $translatedText = (string)$result['translated_text'];
            $telegram = app(TelegramInstanceService::class)->excute();
            $telegram->sendMessage([
                'chat_id' => $this->message['chat']['id'],
                'text' => $translatedText,
                'parse_mode' => 'html'
            ]);
        } catch (Throwable $e) {
            $this->reportTranslateWarning($e);
        }
    }

    private function translate($text, $targetLang): array
    {
        $systemPrompt = "You are a senior bilingual customer-support translator for payments and operations.\n"
            . "Task:\n"
            . "1) Detect source language and output source language code.\n"
            . "2) If input is Chinese: translate it to {$targetLang}.\n"
            . "3) If input is NOT Chinese: translate it to Simplified Chinese (zh-CN).\n"
            . "Rules:\n"
            . "- Return only valid JSON with keys: source_lang, target_lang, translated_text.\n"
            . "- No explanation, no markdown, no code block.\n"
            . "- Translate for real customer-service reading, not word-for-word.\n"
            . "- Make the translation natural, fluent, concise, and easy to understand.\n"
            . "- Preserve the original intent, tone, and important details exactly.\n"
            . "- If the source is fragmented, awkward, or informal, rewrite it into a smooth natural expression in the target language.\n"
            . "- Prefer idiomatic wording commonly used by Chinese-speaking customer service staff.\n"
            . "- Keep product names, platform names, payment terms, currency codes, URLs, account numbers, order IDs, and abbreviations unchanged when that improves clarity.\n"
            . "- For payment/industry terms such as Payin, Payout, VND, USDT, UPI, bank codes, and system terms, preserve the original term if literal translation would sound unnatural or reduce precision.\n"
            . "- Do not over-translate proper nouns, brand names, or system field names.\n"
            . "- If the text contains business negotiation or operational discussion, translate it into clear business Chinese rather than literal Chinese.\n"
            . "- Keep numbers, URLs, order IDs, and special tokens unchanged.\n"
            . "- Keep original line breaks.";

        $content = $this->requestLinkApiResponseContent([
            'model' => config('linkapi.model', 'gpt-4o-mini'),
            'temperature' => 0.2,
            'input' => $systemPrompt . "\n\nInput:\n" . $text,
        ]);
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [
                'source_lang' => 'AUTO',
                'target_lang' => $targetLang,
                'translated_text' => trim($content),
            ];
        }

        return [
            'source_lang' => (string)($decoded['source_lang'] ?? 'AUTO'),
            'target_lang' => (string)($decoded['target_lang'] ?? $targetLang),
            'translated_text' => (string)($decoded['translated_text'] ?? ''),
        ];
    }

    private function requestLinkApiResponseContent(array $payload): string
    {
        $response = (new Client(['timeout' => config('linkapi.request_timeout', 30), 'http_errors' => false]))->post(rtrim((string)config('linkapi.base_uri'), '/') . '/responses', [
            'headers' => $this->linkApiHeaders(),
            'json' => $payload,
        ]);
        $body = (string)$response->getBody();
        $data = json_decode($body, true);

        if ($response->getStatusCode() >= 400 || !is_array($data) || isset($data['error'])) {
            $this->reportOpenAiResponseWarning($response->getStatusCode(), $response->getHeaderLine('Content-Type'), $body);
            return '';
        }

        return $this->extractLinkApiOutputText($data);
    }

    private function extractLinkApiOutputText(array $data): string
    {
        if (!empty($data['output_text'])) {
            return trim((string)$data['output_text']);
        }

        foreach (($data['output'] ?? []) as $output) {
            foreach (($output['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                    return trim((string)$content['text']);
                }
            }
        }

        $this->reportOpenAiResponseWarning(200, 'application/json', json_encode($data, JSON_UNESCAPED_UNICODE));
        return '';
    }

    private function linkApiHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . config('linkapi.api_key'),
            'Content-Type' => 'application/json',
        ];
    }

    private function reportOpenAiResponseWarning(int $statusCode, string $contentType, string $body): void
    {
        app(SystemNoticeService::class)->warning("system_manual_notice", [
            'error' => 'Telegram 翻译接口返回异常',
            'status_code' => $statusCode,
            'content_type' => $contentType,
            'command_text' => mb_substr((string)($this->message['text'] ?? ''), 0, 200),
            'source_text' => mb_substr((string)$this->text, 0, 500),
            'response' => mb_substr($body, 0, 1000),
        ]);
    }

    private function reportTranslateWarning(Throwable $e): void
    {
        app(SystemNoticeService::class)->warning("system_manual_notice", [
            'error' => 'Telegram 翻译任务执行异常',
            'command_text' => mb_substr((string)($this->message['text'] ?? ''), 0, 200),
            'source_text' => mb_substr((string)$this->text, 0, 500),
            'lang' => $this->lang,
            'exception_type' => get_class($e),
            'exception' => $e->getMessage(),
        ]);
    }
}

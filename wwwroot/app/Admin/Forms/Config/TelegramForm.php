<?php

namespace App\Admin\Forms\Config;

use Throwable;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use App\Services\Common\SystemLogService;
use Illuminate\Support\Facades\Validator;
use App\Services\Telegram\TelegramInstanceService;

class TelegramForm extends Form
{
    private const FIELD_KEYS = [
        'telegram_turn_on',
        'telegram_bot_token',
        'base_query_ordernumber_status_text',
    ];

    public function handle(array $input)
    {
        $oldTelegramBotToken = (string)(bob_admin_setting('telegram_bot_token') ?: '');
        $data = $this->normalizeInput($input);
        $needWebhookCheck = intval($data['telegram_turn_on']) === 1;
        $validator = Validator::make($data, [
            'telegram_turn_on' => ['required', 'integer', 'in:0,1'],
            'telegram_bot_token' => ['exclude_unless:telegram_turn_on,1', 'required'],
        ], [
            'telegram_turn_on.required' => '飞机开关不合法',
            'telegram_turn_on.integer' => '飞机开关不合法',
            'telegram_turn_on.in' => '飞机开关不合法',
            'telegram_bot_token.required' => '飞机机器人TOKEN必填',
        ]);
        if ($validator->fails()) {
            return $this->response()->error($validator->errors()->first());
        }

        if ($needWebhookCheck) {
            $webhookResult = $this->syncWebhook((string)$data['telegram_bot_token']);
            if (!$webhookResult['success']) {
                $this->writeConfigLog($data, $oldTelegramBotToken, false, false, $webhookResult['error'] ?? '');
                $errorMessage = '飞机机器人 webhook 校验失败，请检查 TOKEN、网络代理或 Telegram 服务后重试。';
                if (!empty($webhookResult['error'])) {
                    $errorMessage .= ' 详细原因：' . $webhookResult['error'];
                }

                return $this->response()->error($errorMessage);
            }
        }

        bob_admin_setting($data);
        $this->writeConfigLog($data, $oldTelegramBotToken, true, !$needWebhookCheck);

        return $this->response()->success('设置成功')->location();
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('config.telegram');
    }

    public function form()
    {
        $maskedTelegramBotToken = $this->maskSecret((string)(bob_admin_setting('telegram_bot_token') ?: ''));
        $this->confirm('提示', '确定提交？');
        $this->fieldset('机器人基础设置', function (Form $form) use ($maskedTelegramBotToken) {
            $this->radio('telegram_turn_on', '1. 飞机开关')
                ->options([0 => '关闭', 1 => '开启'])
                ->help('开启后，需要继续完成下方 TOKEN 和快捷回复配置；飞机管理员请到后台管理员账号里设置。')
                ->when(1, function () use ($maskedTelegramBotToken) {
                    $this->html($this->currentTokenHtml($maskedTelegramBotToken), '2. 机器人TOKEN')->width(8, 4);
                    $this->password('telegram_bot_token', '3. 更换TOKEN')->attribute(['autocomplete' => 'new-password'])->help('留空表示保持当前 TOKEN 不变；填写新 TOKEN 后会覆盖旧 TOKEN，并自动重新校验 webhook。')->width(8, 4);
                    $this->textarea('base_query_ordernumber_status_text', '4. 查单回复快捷文本')->help('多个快捷回复请换行，一行一条。')->width(8, 4);
                })->width(8, 4);
        });
    }

    public function default()
    {
        return [
            'telegram_turn_on' => bob_admin_setting('telegram_turn_on') ?: 0,
            'telegram_bot_token' => '',
            'base_query_ordernumber_status_text' => bob_admin_setting('base_query_ordernumber_status_text') ?: '',
        ];
    }

    private function normalizeInput(array $input): array
    {
        $data = [];
        foreach (self::FIELD_KEYS as $key) {
            $data[$key] = array_key_exists($key, $input) ? trim((string)$input[$key]) : (bob_admin_setting($key) ?: '');
        }

        $data['telegram_turn_on'] = $data['telegram_turn_on'] === '' ? '0' : $data['telegram_turn_on'];
        if ($data['telegram_bot_token'] === '') {
            $data['telegram_bot_token'] = (string)(bob_admin_setting('telegram_bot_token') ?: '');
        }

        return $data;
    }

    private function syncWebhook(string $telegramBotToken): array
    {
        try {
            $telegram = app(TelegramInstanceService::class)->excute(false, false, $telegramBotToken);
            $webhookUrl = route('telegram.webhook');
            $info = $telegram->getWebhookInfo()->all();

            if (!empty($info['url']) && $info['url'] === $webhookUrl) {
                return ['success' => true];
            }

            if (!empty($info['url'])) {
                $telegram->removeWebhook();
            }

            $result = (bool)$telegram->setWebhook(['url' => $webhookUrl]);

            return [
                'success' => $result,
                'error' => $result ? '' : 'Telegram返回设置失败，目标URL：' . $webhookUrl,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $this->sanitizeTelegramError($e, $telegramBotToken)];
        }
    }

    private function currentTokenHtml(string $maskedToken): string
    {
        $status = $maskedToken === ''
            ? '<span style="display:inline-flex;align-items:center;height:24px;padding:0 10px;border-radius:999px;background:#fff7e6;color:#d48806;font-size:12px;font-weight:600;">未配置</span>'
            : '<span style="display:inline-flex;align-items:center;height:24px;padding:0 10px;border-radius:999px;background:#edfdf5;color:#1f9d63;font-size:12px;font-weight:600;">已配置</span>';
        $token = $maskedToken === ''
            ? '<span style="color:#8c9aad;">暂无可用 TOKEN</span>'
            : '<span style="font-family:Menlo,Monaco,Consolas,monospace;font-size:13px;color:#364a63;letter-spacing:.2px;">' . e($maskedToken) . '</span>';

        return <<<HTML
<div style="display:flex;align-items:center;gap:14px;min-height:42px;padding:10px 14px;border:1px solid #e6ecf3;border-radius:8px;background:#f8fafc;">
    {$status}
    <div style="display:flex;flex-direction:column;gap:3px;">
        {$token}
        <span style="font-size:12px;color:#8c9aad;">为保护密钥安全，页面只展示部分 TOKEN，完整内容不会回填到前端。</span>
    </div>
</div>
HTML;
    }

    private function maskSecret(string $secret): string
    {
        $secret = trim($secret);
        if ($secret === '') {
            return '';
        }

        if (strlen($secret) <= 10) {
            return str_repeat('*', strlen($secret));
        }

        return substr($secret, 0, 6) . str_repeat('*', 8) . substr($secret, -4);
    }

    private function writeConfigLog(array $data, string $oldTelegramBotToken, bool $success, bool $skippedWebhookCheck = false, string $error = ''): void
    {
        app(SystemLogService::class)->logAction(
            actionKey: 'admin.config.telegram.update',
            text: '修改 飞机配置',
            subject: null,
            properties: [
                'telegram_turn_on' => (int)($data['telegram_turn_on'] ?? 0),
                'has_telegram_bot_token' => empty($data['telegram_bot_token']) ? 0 : 1,
                'telegram_bot_token_changed' => $oldTelegramBotToken !== (string)($data['telegram_bot_token'] ?? '') ? 1 : 0,
                'webhook_check_success' => $success ? 1 : 0,
                'webhook_check_skipped' => $skippedWebhookCheck ? 1 : 0,
                'webhook_check_error' => $error,
            ],
            remark: $success ? '修改 飞机配置' : '修改 飞机配置失败：webhook校验失败',
            logType: 'operation',
            actionMethod: 'PUT',
            appType: 'admin',
            user: Admin::user()
        );
    }

    private function sanitizeTelegramError(Throwable $e, string $telegramBotToken): string
    {
        $message = $e::class . ': ' . $e->getMessage();
        if ($telegramBotToken !== '') {
            $message = str_replace($telegramBotToken, $this->maskSecret($telegramBotToken), $message);
        }
        $message = preg_replace('/bot\d+:[A-Za-z0-9_-]+/', 'bot******', $message) ?? $message;
        $message = preg_replace('/\d{6,}:[A-Za-z0-9_-]{20,}/', '******', $message) ?? $message;

        return mb_substr($message, 0, 500);
    }
}

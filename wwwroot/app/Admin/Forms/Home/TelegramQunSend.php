<?php

namespace App\Admin\Forms\Home;

use Throwable;
use Dcat\Admin\Admin;
use RuntimeException;
use App\Models\MerchantInfo;
use Dcat\Admin\Widgets\Form;
use App\Jobs\TelegramQunSendJob;
use App\Services\Common\SystemLogService;
use App\Admin\Controllers\MerchantInfoTelegramTable;

class TelegramQunSend extends Form
{
    public function handle(array $input)
    {
        try {
            $admin = Admin::user();
            $type = intval($input['type'] ?? 1);
            $mids = $this->normalizeMids($input['mids'] ?? []);
            $sendContent = trim((string) ($input['send_content'] ?? ''));
            $sendImage = trim((string) ($input['send_image'] ?? ''));
            $mentionMerchantAdmins = intval($input['mention_merchant_admins'] ?? 0) === 1;

            $this->validateTelegramConfig();
            $this->validateInput($type, $mids, $sendContent, $sendImage);
            $sendImageHash = $this->sendImageHash($sendImage);

            // 只提交到 notice 队列，实际发送由 TelegramQunSendJob 统一处理失败和限频。
            $targetCount = $this->dispatchMerchantMessages($type, $mids, $sendContent, $sendImage, $sendImageHash, $mentionMerchantAdmins);
            if ($targetCount <= 0) {
                return $this->response()->error('没有可发送的商户群');
            }

            app(SystemLogService::class)->logAction(
                actionKey: 'admin.home.telegram.group_send',
                text: '提交 Telegram群发',
                subject: null,
                properties: [
                    'type' => $type,
                    'target_count' => $targetCount,
                    'mids_count' => count($mids),
                    'has_image' => $sendImage === '' ? 0 : 1,
                    'has_content' => $sendContent === '' ? 0 : 1,
                    'mention_merchant_admins' => $mentionMerchantAdmins ? 1 : 0,
                ],
                remark: '提交 Telegram群发',
                logType: 'operation',
                actionMethod: 'POST',
                appType: 'admin',
                user: $admin
            );

            return $this->response()->success('发送成功');
        } catch (Throwable $e) {
            return $this->response()->error($e->getMessage());
        }
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('telegramQunSend');
    }

    public function form()
    {
        $this->radio('type', '发送类型')->options([1 => '全部商户', 2 => '部分商户'])->default(1)->when(2, function () {
            $this->multipleSelectTable('mids', '选择商户')->title('选择商户')->from(MerchantInfoTelegramTable::make())->options(function ($v) {
                if (!$v) {
                    return [];
                }

                $mids = is_string($v) ? explode(',', $v) : (array) $v;
                return MerchantInfo::whereIn('merchant_user_id', $mids)->get(['merchant_user_id', 'currency_id', 'name'])->pluck('bname', 'merchant_user_id');
            })->pluck('bname', 'merchant_user_id');
        });

        $this->image('send_image', '发送图片')->uniqueName()->autoUpload()->maxSize(5024)->help('上传图片不超过5M');
        $this->textarea('send_content', '群发文本');
        $this->switch('mention_merchant_admins', '提醒商户群管理员')->default(0)->help('开启后，每个商户群会自动读取已授权的商户群管理员并追加到消息末尾。');
    }

    public function default()
    {
        return [
            'type' => 1,
            'mids' => '',
            'send_image' => '',
            'send_content' => '',
            'mention_merchant_admins' => 0,
        ];
    }

    private function validateTelegramConfig(): void
    {
        if (intval(config('telegram.turn_on', 0)) === 0) {
            throw new RuntimeException('请开启Telegram机器人配置开关');
        }

        if (empty(config('telegram.telegram_bot_token'))) {
            throw new RuntimeException('请配置Telegram机器人Token');
        }
    }

    private function validateInput(int $type, array $mids, string $sendContent, string $sendImage): void
    {
        if (!in_array($type, [1, 2], true)) {
            throw new RuntimeException('发送类型不正确');
        }

        if ($sendContent === '' && $sendImage === '') {
            throw new RuntimeException('群发文本或图片至少有一项不能为空');
        }

        if ($type === 2 && empty($mids)) {
            throw new RuntimeException('请选择需要群发的商家！');
        }
    }

    private function dispatchMerchantMessages(int $type, array $mids, string $sendContent, string $sendImage, string $sendImageHash, bool $mentionMerchantAdmins): int
    {
        $count = 0;

        $this->targetMerchantQuery($type, $mids)->chunkById(200, function ($merchants) use (&$count, $sendContent, $sendImage, $sendImageHash, $mentionMerchantAdmins) {
            foreach ($merchants as $merchant) {
                dispatch(new TelegramQunSendJob($this->messagePayload($merchant, $sendContent, $sendImage, $sendImageHash, $mentionMerchantAdmins)))->onQueue('notice');
                $count++;
            }
        }, 'merchant_user_id');

        return $count;
    }

    private function targetMerchantQuery(int $type, array $mids)
    {
        return MerchantInfo::query()
            ->where('telegram_group_id', '<>', 0)
            ->when($type === 2, fn($query) => $query->whereIn('merchant_user_id', $mids))
            ->select(['telegram_group_id', 'merchant_user_id', 'name']);
    }

    private function messagePayload(MerchantInfo $merchant, string $sendContent, string $sendImage, string $sendImageHash, bool $mentionMerchantAdmins): array
    {
        $data = [
            'merchant_id' => $merchant->merchant_user_id,
            'merchant_name' => $merchant->name,
            'telegram_group_id' => $merchant->telegram_group_id,
            'mention_merchant_admins' => $mentionMerchantAdmins ? 1 : 0,
        ];

        if ($sendImage !== '') {
            $data['send_image'] = $sendImage;
            $data['send_image_hash'] = $sendImageHash;
        }

        if ($sendContent !== '') {
            $data['send_content'] = $sendContent;
        }

        return $data;
    }

    private function sendImageHash(string $sendImage): string
    {
        if ($sendImage === '') {
            return '';
        }

        $relativePath = ltrim(str_replace('\\', '/', $sendImage), '/');
        if (!str_starts_with($relativePath, 'images/') || str_contains($relativePath, '../')) {
            throw new RuntimeException('群发图片路径不正确，请重新上传');
        }

        $imageRoot = realpath(storage_path('app/public/images'));
        $imagePath = realpath(storage_path('app/public/' . $relativePath));
        if ($imageRoot === false || $imagePath === false || !str_starts_with($imagePath, $imageRoot . DIRECTORY_SEPARATOR) || !is_file($imagePath) || !is_readable($imagePath)) {
            throw new RuntimeException('群发图片不存在或无法读取，请重新上传');
        }

        $hash = hash_file('sha256', $imagePath);
        if ($hash === false) {
            throw new RuntimeException('群发图片读取失败，请重新上传');
        }

        return $hash;
    }

    private function normalizeMids($mids): array
    {
        if (is_string($mids)) {
            $mids = explode(',', $mids);
        }

        return collect((array) $mids)
            ->map(fn($mid) => intval($mid))
            ->filter(fn($mid) => $mid > 0)
            ->unique()
            ->values()
            ->all();
    }
}

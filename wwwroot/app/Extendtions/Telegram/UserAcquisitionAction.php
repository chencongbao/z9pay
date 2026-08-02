<?php

namespace App\Extendtions\Telegram;

use App\Models\User;
use App\Traits\TelegramTrait;

class UserAcquisitionAction
{
    use TelegramTrait;

    protected $telegram;

    public function __construct($telegram)
    {
        $this->telegram = $telegram;
    }

    public function excute($message = [], $group_type = 0): void
    {
        $text = (string)($message['text'] ?? '');
        $status = $this->parseStatus($text);
        if ($status === null) {
            return;
        }

        if (!$this->checkGroupCanOperate($message, intval($group_type))) {
            return;
        }

        $field = $this->resolveUserField($message, $text);
        if ($field === '') {
            $this->reply($message, '金主不存在，操作失败');
            return;
        }

        $user = $this->findUser($field);
        if (!$user || !$this->checkUserBindGroup($message, $user)) {
            return;
        }

        if (intval($user->acquisition_status) !== $status) {
            User::whereKey($user->id)->update(['acquisition_status' => $status]);
        }

        $this->reply($message, '金主【' . $this->html($user->bname) . '】，收款已' . ($status === 1 ? '开启' : '关闭'));
    }

    private function parseStatus(string $text): ?int
    {
        if (mb_substr($text, 0, 4) === '收款开启') {
            return 1;
        }

        if (mb_substr($text, 0, 4) === '收款关闭') {
            return 0;
        }

        return null;
    }

    private function checkGroupCanOperate(array $message, int $groupType): bool
    {
        if ($groupType === 1) {
            $this->reply($message, '商家群无法操作此命令');
            return false;
        }

        if ($groupType === 0) {
            $this->reply($message, '此群组还未绑定金主');
            return false;
        }

        return true;
    }

    private function resolveUserField(array $message, string $text): string
    {
        $field = trim(str_replace(['收款开启', '+', '收款关闭'], '', $text));
        if ($this->checkIsManager($message)) {
            return $field;
        }

        $user = User::where('telegram_group_id', $message['chat']['id'] ?? 0)
            ->where('telegram_user_id', $message['from']['id'] ?? 0)
            ->first(['id']);
        if (!$user) {
            $this->reply($message, '您未绑定金主，无法操作此命令', false);
            return '';
        }

        return (string)$user->id;
    }

    private function findUser(string $field): ?User
    {
        return User::where(function ($query) use ($field) {
            $query->where('id', $field)->orWhere('username', $field);
        })->first(['id', 'name', 'username', 'telegram_group_id', 'acquisition_status']);
    }

    private function checkUserBindGroup(array $message, User $user): bool
    {
        if (intval($user->telegram_group_id) === 0) {
            $this->reply($message, '金主【' . $this->html($user->bname) . '】，未绑定群组，操作失败');
            return false;
        }

        if (intval($user->telegram_group_id) !== intval($message['chat']['id'] ?? 0)) {
            $this->reply($message, '金主【' . $this->html($user->bname) . '】，未绑定当前群组，操作失败');
            return false;
        }

        return true;
    }

    private function reply(array $message, string $text, bool $html = true): void
    {
        $chatId = $message['chat']['id'] ?? 0;
        if (!$chatId) {
            return;
        }

        $payload = ['chat_id' => $chatId, 'text' => $text];
        if ($html) {
            $payload['parse_mode'] = 'html';
        }
        if (!empty($message['message_id'])) {
            $payload['reply_to_message_id'] = $message['message_id'];
        }

        $this->telegram->sendMessage($payload);
    }

    private function html($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

<?php

namespace App\Admin\Forms\Channel;

use Dcat\Admin\Admin;
use App\Models\Channel;
use Dcat\Admin\Widgets\Form;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Contracts\LazyRenderable;

class ChannelTransferCheckInfoForm extends Form implements LazyRenderable
{
    use LazyWidget;

    private bool $channelLoaded = false;

    private ?Channel $channel = null;

    public function form()
    {
        $this->disableResetButton();
        $this->disableSubmitButton();

        Admin::script(<<<'JS'
$(document).off('click.channel-transfer-check-copy', '.copy-text').on('click.channel-transfer-check-copy', '.copy-text', function () {
    var content = $(this).data('content');
    var $temp = $('<input>');

    $('body').append($temp);
    $temp.val(content).select();
    document.execCommand('copy');
    $temp.remove();

    Dcat.success('复制成功');
});
JS);

        $channel = $this->channel();
        $channelId = $channel ? $channel->id : 0;
        $appSecret = $channel ? $channel->appsecret : '';

        $this->text('id', '反查对接CID')->disable()->prepend($this->copyButton($channelId));
        $this->text('appsecret', '反查对接key')->disable()->prepend($this->copyButton($appSecret));
    }

    protected function authorize($user): bool
    {
        return Admin::user()->can('channels-index');
    }

    public function default()
    {
        $channel = $this->channel();

        return [
            'id' => $channel ? $channel->id : 0,
            'appsecret' => $channel ? $channel->appsecret : '',
        ];
    }

    private function channel(): ?Channel
    {
        if ($this->channelLoaded) {
            return $this->channel;
        }

        $this->channelLoaded = true;
        $channelId = intval($this->payload['id'] ?? 0);
        if ($channelId <= 0) {
            return null;
        }

        $this->channel = Channel::query()->whereKey($channelId)->first(['id', 'appsecret']);

        return $this->channel;
    }

    private function copyButton($content): string
    {
        return '<span style="cursor: pointer;" class="copy-text" data-content="' . e((string)$content) . '">复制</span>';
    }
}

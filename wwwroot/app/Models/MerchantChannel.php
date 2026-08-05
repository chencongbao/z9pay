<?php

namespace App\Models;

use Dcat\Admin\Admin;
use App\Traits\ModelTraits;
use App\Traits\ActivityLogTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MerchantChannel extends Model
{
	use ModelTraits, SoftDeletes, ActivityLogTrait;

    protected $table = 'merchant_channels';

    protected $guarded = [];

    public function merchant_info(){
        return $this->belongsTo(MerchantInfo::class,'merchant_user_id');
    }

    public function channel(){
        return $this->belongsTo(Channel::class,'channel_id');
    }

    public function amountFeeInfo(): string
    {
        if ((int) $this->payment_id === 7) {
            return $this->renderAmountFeeInfo('代付', '#d4380d', [
                ['单笔限额', bob_unit_format($this->collection_min_amount) . ' - ' . bob_unit_format($this->collection_max_amount)],
                ['额外手续费', bob_unit_format($this->fee)],
            ]);
        }

        return $this->renderAmountFeeInfo('代收', '#21b978', [
            ['单笔限额', bob_unit_format($this->pay_min_amount) . ' - ' . bob_unit_format($this->pay_max_amount)],
            ['额外手续费', bob_unit_format($this->deposit_fee)],
        ]);
    }

    private function renderAmountFeeInfo(string $type, string $color, array $data): string
    {
        $html = '<div style="display:inline-flex;align-items:center;gap:10px;white-space:nowrap;line-height:22px;">'
            . '<span style="display:inline-block;padding:1px 7px;border-radius:10px;background:' . e($color) . ';color:#fff;font-size:12px;font-weight:700;">' . e($type) . '</span>';
        foreach ($data as [$label, $value]) {
            $html .= '<span><span class="text-muted">' . e($label) . '：</span>' . e($value) . '</span>';
        }

        return $html . '</div>';
    }

    public function transferModeLabel(): string
    {
        if ((int) $this->payment_id !== 7 || !$this->merchant_info) {
            return '';
        }

        if ((int) $this->merchant_info->auto_transfer !== 0) {
            return '';
        }

        return '<span style="display:inline-block;margin-left:6px;padding:2px 7px;border-radius:10px;background:#fff1f0;color:#d4380d;font-size:12px;font-weight:600;white-space:nowrap;">手动代付</span>';
    }

    public function floatConfigInfo(bool $canSwitch = false): string
    {
        $merchantFloatType = (int) optional($this->merchant_info)->amount_float_type;
        if ($merchantFloatType <= 0) {
            return '<span class="float-tag float-tag-close">已关闭</span>';
        }

        return $this->renderFloatInfoRows([
            ['', $this->floatTypeText($merchantFloatType)],
            ['最大差额', bob_unit_format(optional($this->merchant_info)->float_amount ?? 0)],
            ['通道开关', $canSwitch ? $this->floatStatusSwitchHtml() : $this->floatStatusText()],
        ]);
    }

    private function renderFloatInfoRows(array $rows): string
    {
        $html = '<div class="merchant-channel-float-info">';
        foreach ($rows as [$key, $value]) {
            $label = $key !== '' ? '<span class="float-key">' . e($key) . '：</span>' : '';
            $html .= '<div class="float-row">' . $label . '<span class="float-value">' . $value . '</span></div>';
        }

        return $html . '</div>';
    }

    private function floatTypeText(int $type): string
    {
        return match ($type) {
            1 => '<span class="float-tag float-tag-open">向上浮动</span>',
            2 => '<span class="float-tag float-tag-open">向下浮动</span>',
            default => '<span class="float-tag float-tag-close">关闭</span>',
        };
    }

    private function floatStatusText(): string
    {
        return (int)$this->float_status === 1
            ? '<span class="float-tag float-tag-open">开启</span>'
            : '<span class="float-tag float-tag-close">关闭</span>';
    }

    private function floatStatusSwitchHtml(): string
    {
        $checked = (int)$this->float_status === 1 ? ' checked' : '';
        $url = Admin::app()->getRoute('merchant-channels.update', ['merchant_channel' => $this->id]);

        return '<label class="merchant-channel-float-switch" title="通道浮动开关">'
            . '<input type="checkbox" data-url="' . e($url) . '"' . $checked . '>'
            . '<span></span>'
            . '</label>';
    }

}

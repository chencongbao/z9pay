<?php

namespace App\Models;

use App\Traits\ActivityLogTrait;
use Illuminate\Database\Eloquent\Model;
use Dcat\Admin\Traits\HasDateTimeFormatter;

class ChannelRate extends Model
{
    use HasDateTimeFormatter, ActivityLogTrait;

    protected $table = 'channel_rates';

    protected $appends = ['payment_name'];

    protected $casts = [
        'rate_ranges' => 'array',
    ];

    public function getRateRangesAttribute($value): array
    {
        $ranges = is_array($value) ? $value : json_decode($value ?: '[]', true);
        if (!is_array($ranges)) {
            return [];
        }

        if ((int)$this->type === 1) {
            foreach ($ranges as &$range) {
                if (is_array($range)) {
                    $range['rate'] = $range['fixed_rate'] ?? 0;
                }
            }
        }

        return $ranges;
    }

    public function getPaymentNameAttribute()
    {
        return self::resolvePaymentName($this->payment_id, $this->relationLoaded('channel') ? $this->channel : null);
    }

    public static function resolvePaymentName($paymentId, ?object $channel = null): string
    {
        if ($channel && !empty($channel->classname)) {
            $path = base_path('vendor/richard/payment/src/Channel/' . $channel->classname . '.php');
            $classname = 'Richard\\Payment\\Channel\\' . $channel->classname;

            if (file_exists($path) && class_exists($classname)) {
                try {
                    $name = trim((string)(new $classname())->getChanelCoder($paymentId));
                    if ($name !== '') {
                        return $name;
                    }
                } catch (\Throwable $exception) {
                    // 渠道类解析失败时不影响后台展示，继续回退全局支付配置。
                }
            }
        }

        return self::formatPaymentName($paymentId);
    }

    public static function formatPaymentName($paymentId): string
    {
        $payment = collect(config('payment', []))->firstWhere('id', (int)$paymentId);
        if (!$payment) {
            return '未知通道【#' . $paymentId . '】';
        }

        $name = (string)($payment['name'] ?? '');
        $code = (string)($payment['code'] ?? '');

        return $code === '' ? $name : $name . '【' . $code . '】';
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}

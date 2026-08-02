<?php

namespace App\Models;

use App\Traits\ModelTraits;
use App\Traits\ActivityLogTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserBank extends Model
{
	use ModelTraits, SoftDeletes, ActivityLogTrait;

    protected $guarded = [];

    protected $table = 'user_banks';

    protected $appends = ["payment_qrcode_format","bname","bnamebalance"];


    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function bank_code()
    {
        return $this->belongsTo(BankCode::class,'bank_id');
    }

    public function getPaymentQrcodeFormatAttribute()
    {
        return $this->paymentQrcodeUrl();
    }

    public function paymentQrcodeUrl(): ?string
    {
        if (empty($this->payment_qrcode)) {
            return null;
        }

        $path = ltrim((string) $this->payment_qrcode, '/');
        if (!Storage::disk('admin')->exists($path)) {
            return null;
        }

        return Storage::disk('admin')->url($path);
    }

    public function getBnameAttribute()
    {
        return self::formatDisplayName((int) $this->id, (string) $this->name, (int) $this->account_type, optional($this->bank_code)->name, $this->card_no);
    }

    public static function formatDisplayName(int $id, string $name, int $accountType, ?string $bankName, ?string $cardNo): string
    {
        $displayName = "【#{$id}】{$name}";
        if (in_array($accountType, [3, 5, 28], true)) {
            return $displayName;
        }

        return "{$displayName}【{$bankName}：{$cardNo}】";
    }

    public function getBnamebalanceAttribute(){
        return "【#".$this->id."】".$this->name."【".bob_unit_format($this->balance_amount)."】";
    }

}

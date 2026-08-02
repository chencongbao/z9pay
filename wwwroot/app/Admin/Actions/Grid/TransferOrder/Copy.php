<?php

namespace App\Admin\Actions\Grid\TransferOrder;

use Dcat\Admin\Admin;
use App\Models\BankCode;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Support\Helper;

class Copy extends RowAction
{
    private static array $bankCodeMap = [];

    protected function script()
    {
        return <<<'JS'
$(document).off('click.transfer-order-copy', '.grid-column-copyable').on('click.transfer-order-copy', '.grid-column-copyable', function () {
    var content = $(this).data('content') || '';
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(content).then(function () {
            Dcat.success('已拷贝卡信息至剪切板');
        }).catch(function () {
            copyTransferOrderCardFallback(content);
        });
        return;
    }
    copyTransferOrderCardFallback(content);
});

function copyTransferOrderCardFallback(content) {
    var $temp = $('<input>');
    $('body').append($temp);
    $temp.val(content).select();
    document.execCommand('copy');
    $temp.remove();
    Dcat.success('已拷贝卡信息至剪切板');
}
JS;
    }

    public function render()
    {
        Admin::script($this->script());

        $content = Helper::htmlEntityEncode($this->copyContent());
        return '<a href="javascript:void(0);" class="grid-column-copyable" data-content="' . $content . '">拷贝卡信息</a>';
    }

    private function copyContent(): string
    {
        return trim(sprintf(
            '卡号：%s 银行：%s 姓名：%s 金额：%s',
            $this->row['card_no'] ?? '',
            $this->bankName(),
            $this->row['holder_name'] ?? '',
            $this->row['amount'] ?? ''
        ));
    }

    private function bankName(): string
    {
        $bankName = trim((string)($this->row['bank_name'] ?? ''));
        if ($bankName !== '') {
            return $bankName;
        }

        $bankCode = trim((string)($this->row['bank_code'] ?? ''));
        if ($bankCode === '') {
            return '';
        }

        // 列表行没有银行名称时，同一个请求内只读取一次银行编码映射，避免每行查询。
        if (empty(self::$bankCodeMap)) {
            self::$bankCodeMap = BankCode::pluck('name', 'code')->toArray();
        }

        return self::$bankCodeMap[$bankCode] ?? self::$bankCodeMap[strtoupper($bankCode)] ?? '';
    }
}

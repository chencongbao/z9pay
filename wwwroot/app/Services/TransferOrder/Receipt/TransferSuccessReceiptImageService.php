<?php

namespace App\Services\TransferOrder\Receipt;

use Carbon\Carbon;
use RuntimeException;
use App\Models\TransferOrder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Symfony\Component\Process\Process;

class TransferSuccessReceiptImageService
{
    public function make(TransferOrder $order, string $lang = 'zh_CN'): string
    {
        $lang = $this->normalizeLang($lang);
        $htmlPath = $this->htmlPath($order, $lang);
        $imagePath = $this->imagePath($order, $lang);

        File::ensureDirectoryExists(dirname($htmlPath));
        File::ensureDirectoryExists(dirname($imagePath));
        File::put($htmlPath, View::make('admin.transfer-order.receipt.success', $this->viewData($order, $lang))->render());

        $process = new Process([$this->nodeBinary(), $this->renderScriptPath(), $htmlPath, $imagePath], base_path(), $this->processEnv());
        $process->setTimeout($this->timeout());
        $process->run();

        if (!$process->isSuccessful() || !is_file($imagePath)) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: '代付成功回执单生成失败');
        }

        return $imagePath;
    }

    private function viewData(TransferOrder $order, string $lang): array
    {
        $amount = number_format(floatval($order->actual_amount ?: $order->amount), 2, '.', ',');
        $currency = $this->currency((int)$order->currency_id);
        $currencyCode = $this->currencyCode($currency);
        $currencySymbol = $this->currencySymbol($currency, $currencyCode);
        $successTime = intval($order->success_time) > 0
            ? Carbon::createFromTimestamp(intval($order->success_time))->format('Y-m-d H:i:s O')
            : now()->format('Y-m-d H:i:s O');
        $bankReference = trim((string)($order->utr ?: ($order->channel_ordernumber ?: $order->ordernumber)));
        $bankCode = $this->blankToDash($order->bank_code ?: $order->bank_name);
        $cardNo = $this->blankToDash($order->card_no);
        $description = $lang === 'zh_CN'
            ? '银行转账至账户 ' . $cardNo . '，银行编码 ' . $bankCode . '，参考号 ' . $this->blankToDash($bankReference)
            : 'Bank transfer to account ' . $cardNo . ' at ' . $bankCode . '|refId' . $this->blankToDash($bankReference);

        return [
            'labels' => $this->labels($lang),
            'amount' => $amount,
            'amount_text' => trim($currencySymbol . ' ' . $amount),
            'currency_code' => $currencyCode,
            'currency_symbol' => $currencySymbol,
            'show_ph_footer' => $currencyCode === 'PHP',
            'success_time' => $successTime,
            'holder_name' => $this->blankToDash($order->holder_name),
            'card_no' => $cardNo,
            'bank_code' => $bankCode,
            'bank_reference' => $this->blankToDash($bankReference),
            'description' => $description,
        ];
    }

    private function htmlPath(TransferOrder $order, string $lang): string
    {
        return storage_path('app/transfer-receipts/html/' . $this->filename($order, $lang, 'html'));
    }

    private function imagePath(TransferOrder $order, string $lang): string
    {
        return storage_path('app/transfer-receipts/images/' . $this->filename($order, $lang, 'png'));
    }

    private function filename(TransferOrder $order, string $lang, string $extension): string
    {
        $ordernumber = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$order->ordernumber) ?: $order->id;
        return $order->id . '-' . $ordernumber . '-' . $lang . '.' . $extension;
    }

    private function blankToDash($value): string
    {
        $value = trim((string)$value);
        return $value === '' ? '-' : $value;
    }

    private function nodeBinary(): string
    {
        return base_path('bin/node');
    }

    private function renderScriptPath(): string
    {
        $scriptPath = base_path('bin/render-transfer-receipt.js');
        if (!is_file($scriptPath)) {
            throw new RuntimeException('代付成功回执单渲染脚本不存在，请同步文件：bin/render-transfer-receipt.js');
        }

        return $scriptPath;
    }

    private function timeout(): int
    {
        return max(5, intval(config('transfer-receipt.timeout', 30)));
    }

    private function processEnv(): array
    {
        $chromeBinary = trim((string)config('transfer-receipt.chrome_binary', ''));
        if ($chromeBinary === '') {
            return [];
        }

        return ['TRANSFER_RECEIPT_CHROME_BINARY' => $chromeBinary];
    }

    private function normalizeLang(string $lang): string
    {
        return $lang === 'en' ? 'en' : 'zh_CN';
    }

    private function labels(string $lang): array
    {
        if ($lang === 'en') {
            return [
                'title' => 'Transaction Successful!',
                'transfer_to' => 'TRANSFER TO',
                'account_name' => 'Account Name:',
                'account_number' => 'Account Number:',
                'amount' => 'Amount:',
                'transaction_details' => 'TRANSACTION DETAILS',
                'bank_code' => 'Bank Code:',
                'bank_reference' => 'Bank Reference:',
                'bank_result' => 'Bank Result:',
                'description' => 'Description:',
                'success' => 'SUCCESS',
            ];
        }

        return [
            'title' => '交易成功！',
            'transfer_to' => '转账至',
            'account_name' => '收款姓名：',
            'account_number' => '收款账号：',
            'amount' => '金额：',
            'transaction_details' => '交易详情',
            'bank_code' => '银行编码：',
            'bank_reference' => '银行参考号：',
            'bank_result' => '银行结果：',
            'description' => '说明：',
            'success' => '成功',
        ];
    }

    private function currency(int $currencyId): array
    {
        $currency = collect(config('default.currency'))->firstWhere('id', $currencyId);

        return is_array($currency) ? $currency : [];
    }

    private function currencyCode(array $currency): string
    {
        $code = strtoupper(trim((string)($currency['short_name'] ?? '')));

        return $code;
    }

    private function currencySymbol(array $currency, string $currencyCode): string
    {
        $amountUnit = trim((string)($currency['amount_unit'] ?? ''));

        return $amountUnit !== '' ? $amountUnit : $currencyCode;
    }
}

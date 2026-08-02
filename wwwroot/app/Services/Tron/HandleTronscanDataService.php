<?php

namespace App\Services\Tron;

use App\Traits\ServiceTraits;
use App\Jobs\HandleListeningAddressResultJob;
use App\Services\Cache\ListeningTronAddress\GetListeningTronAddressService;

class HandleTronscanDataService
{
    use ServiceTraits;

    private const USDT_CONTRACT_ADDRESS = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    public function excute(array $data = []): void
    {
        if (empty($data)) {
            return;
        }

        $this->listeningTronAddress($data);
    }

    private function listeningTronAddress(array $data = []): void
    {
        $listeningAddressMap = app(GetListeningTronAddressService::class)->addressMap();
        if (empty($listeningAddressMap)) {
            return;
        }

        foreach ($data as $value) {
            if (!$this->isUsdtTransfer($value)) {
                continue;
            }

            if (isset($listeningAddressMap[$value['from_address']])) {
                $this->dispatchListeningResult($value, '支出', $value['from_address']);
            }

            if (isset($listeningAddressMap[$value['to_address']])) {
                $this->dispatchListeningResult($value, '收入', $value['to_address']);
            }
        }
    }

    private function isUsdtTransfer($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach (['type', 'contract_address', 'from_address', 'to_address', 'amount', 'tx_id'] as $field) {
            if (!array_key_exists($field, $value)) {
                return false;
            }
        }

        return (int)$value['type'] === 1 && $value['contract_address'] === self::USDT_CONTRACT_ADDRESS;
    }

    private function dispatchListeningResult(array $value, string $type, string $address): void
    {
        dispatch(new HandleListeningAddressResultJob([
            'type' => $type,
            'address' => $address,
            'amount' => $value['amount'],
            'tx_id' => $value['tx_id'],
            'from_address' => $value['from_address'],
            'to_address' => $value['to_address'],
            'time' => date('Y-m-d H:i:s'),
        ]))->onQueue('query');
    }
}

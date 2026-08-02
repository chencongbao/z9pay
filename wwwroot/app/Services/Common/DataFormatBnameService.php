<?php

namespace App\Services\Common;

use App\Traits\ServiceTraits;

class DataFormatBnameService
{
    use ServiceTraits;

    public function excute($data = [])
    {
        if (empty($data)) {
            return $data;
        }

        $currencies = collect(config('default.currency', []))->keyBy('id');
        if ($this->is_2d_array($data)) {
            return collect($data)->map(fn ($item) => $this->formatItem($item, $currencies))->toArray();
        }

        return $this->formatItem($data, $currencies);
    }

    public function is_2d_array($array): bool
    {
        if (!is_array($array) || empty($array)) {
            return false;
        }

        $nonArrayItems = array_filter($array, fn ($item) => !is_array($item));

        return count($nonArrayItems) === 0;
    }

    private function formatItem(array $item, $currencies): array
    {
        $bname = '';
        if (isset($item['id'])) {
            $bname .= '【#'.$item['id'].'】';
        } elseif (isset($item['merchant_user_id'])) {
            $bname .= '【#'.$item['merchant_user_id'].'】';
        }
        if (isset($item['username'])) {
            $bname .= '【'.$item['username'].'】';
        }
        if (isset($item['coder'])) {
            $bname .= '【'.$item['coder'].'】';
        }
        if (isset($item['currency_id'])) {
            $bname .= '【'.optional($currencies->get($item['currency_id']))->offsetGet('name').'】';
        }
        if (isset($item['name'])) {
            $bname .= $item['name'];
        }

        $item['bname'] = $bname;

        return $item;
    }
}

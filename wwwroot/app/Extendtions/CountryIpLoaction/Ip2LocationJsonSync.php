<?php

namespace App\Extendtions\CountryIpLoaction;

use App\Models\IpCountryRange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class Ip2LocationJsonSync
{
    /**
     * 从 JSON 数据集同步某国家 IP 段
     *
     * @return array{country:string,rows:int,total_ip:int,url:string}
     */
    public function syncCountry($currency_id = 0)
    {
        $url = $this->defaultUrl($currency_id);

        $resp = Http::timeout(60)->retry(2, 500)->acceptJson()->get($url);

        if (!$resp->ok()) {
            throw new \Exception("Fetch failed: {$resp->status()} {$url}");
        }

        $json = $resp->json();
        if ($json === null) {
            throw new \Exception("Invalid JSON: {$url}");
        }

        // 兼容：顶层就是数组；或 {data: [...] }；或其他包裹字段
        $items = $this->extractItems($json);
        if (empty($items)) {
            throw new \Exception("No items found in JSON: {$url}");
        }

        // 解析出 ranges：统一成 begin_long/end_long，并尽量保留 begin_ip/end_ip
        $ranges = [];
        foreach ($items as $it) {
            $range = $this->parseRangeItem($it);
            if (!$range) continue;

            if ($range['end_long'] < $range['begin_long']) continue;

            $range['total_count'] = $range['end_long'] - $range['begin_long'] + 1;
            $ranges[] = $range;
        }

        if (empty($ranges)) {
            throw new \Exception("No valid ranges parsed from JSON: {$url}");
        }

        usort($ranges, fn($a, $b) => $a['begin_long'] <=> $b['begin_long']);

        return DB::transaction(function () use ($ranges, $currency_id, $url) {
            // 简单策略：每次全量覆盖（最稳）
            IpCountryRange::where('currency_id', $currency_id)->delete();

            $cdf = 0;
            $insert = [];

            foreach ($ranges as $r) {
                $cdf += (int)$r['total_count'];

                $insert[] = [
                    'currency_id' => $currency_id,
                    'begin_ip' => $r['begin_ip'],
                    'end_ip' => $r['end_ip'],
                    'begin_long' => $r['begin_long'],
                    'end_long' => $r['end_long'],
                    'total_count' => $r['total_count'],
                    'cdf_end' => $cdf,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($insert, 2000) as $chunk) {
                IpCountryRange::insert($chunk);
            }

            return [
                'country' => $currency_id,
                'rows' => count($insert),
                'total_ip' => $cdf,
                'url' => $url,
            ];
        });
    }

    public function defaultUrl($currency_id = 0)
    {
        $map = [
            3 => 'IN', // INR
            1 => 'CN', // INR
        ];

        $countryCode = $map[$currency_id] ?? 'IN';

        return "https://assets-lite.ip2location.com/datasets/{$countryCode}.json";
    }

    /**
     * @return array<int, mixed>
     */
    protected function extractItems(mixed $json): array
    {
        if (is_array($json)) {
            // 可能是 list，也可能是 assoc
            if ($this->isListArray($json)) return $json;

            // 常见：{data:[...]} / {ranges:[...]} / {result:[...]}
            foreach (['data', 'ranges', 'result', 'items'] as $k) {
                if (isset($json[$k]) && is_array($json[$k]) && $this->isListArray($json[$k])) {
                    return $json[$k];
                }
            }
        }
        return [];
    }

    protected function isListArray(array $arr): bool
    {
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /**
     * 兼容多种 item 格式：
     * 1) ["1.0.0.0","1.0.0.255", ...]
     * 2) {"begin":"1.0.0.0","end":"1.0.0.255"} / {"start","end"} / {"from","to"}
     * 3) {"ip_from":123,"ip_to":456}
     *
     * @return array{begin_ip:?string,end_ip:?string,begin_long:int,end_long:int}|null
     */
    protected function parseRangeItem(mixed $it): ?array
    {
        // case: [begin,end,...]
        if (is_array($it) && $this->isListArray($it) && count($it) >= 2) {
            return $this->parseBeginEnd($it[0], $it[1]);
        }

        // case: {..}
        if (is_array($it) && !$this->isListArray($it)) {
            // numeric long
            if (isset($it['ip_from'], $it['ip_to'])) {
                $from = $this->toUInt($it['ip_from']);
                $to = $this->toUInt($it['ip_to']);
                if ($from <= 0 || $to <= 0) return null;

                return [
                    'begin_ip' => null,
                    'end_ip' => null,
                    'begin_long' => $from,
                    'end_long' => $to,
                ];
            }

            foreach ([['begin', 'end'], ['start', 'end'], ['from', 'to']] as [$a, $b]) {
                if (isset($it[$a], $it[$b])) {
                    return $this->parseBeginEnd($it[$a], $it[$b]);
                }
            }
        }

        return null;
    }

    /**
     * @return array{begin_ip:?string,end_ip:?string,begin_long:int,end_long:int}|null
     */
    protected function parseBeginEnd(mixed $begin, mixed $end): ?array
    {
        // begin/end 是点分IP
        if (is_string($begin) && is_string($end) && $this->isIpv4($begin) && $this->isIpv4($end)) {
            $from = $this->ipToUnsignedLong($begin);
            $to = $this->ipToUnsignedLong($end);
            if ($from <= 0 || $to <= 0) return null;

            return [
                'begin_ip' => $begin,
                'end_ip' => $end,
                'begin_long' => $from,
                'end_long' => $to,
            ];
        }

        // begin/end 是数字 long（字符串/数字都兼容）
        $from = $this->toUInt($begin);
        $to = $this->toUInt($end);
        if ($from > 0 && $to > 0) {
            return [
                'begin_ip' => null,
                'end_ip' => null,
                'begin_long' => $from,
                'end_long' => $to,
            ];
        }

        return null;
    }

    protected function isIpv4(string $ip): bool
    {
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    protected function ipToUnsignedLong(string $ip): int
    {
        $long = ip2long($ip);
        if ($long === false) return 0;
        return (int)sprintf('%u', $long); // unsigned
    }

    protected function toUInt(mixed $v): int
    {
        if (is_int($v)) return $v >= 0 ? $v : 0;
        if (is_numeric($v)) {
            $n = (int)$v;
            return $n >= 0 ? $n : 0;
        }
        return 0;
    }
}

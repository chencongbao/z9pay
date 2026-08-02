<?php

namespace App\Extendtions\Tron;

use Exception;

class TronAddressValidator
{
    /**
     * Base58 字符表（TRON / BTC 通用）
     */
    private const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /**
     * 主入口
     */
    public static function isValid(string $address): bool
    {
        try {
            self::validate($address);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 严格校验（失败抛异常）
     */
    public static function validate(string $address): void
    {
        // 1️⃣ 基础格式
        if (strlen($address) < 34 || strlen($address) > 35) {
            throw new Exception('INVALID_LENGTH');
        }

        if ($address[0] !== 'T') {
            throw new Exception('INVALID_PREFIX');
        }

        if (!preg_match('/^T[' . preg_quote(self::BASE58_ALPHABET, '/') . ']+$/', $address)) {
            throw new Exception('INVALID_CHARSET');
        }

        // 2️⃣ Base58 解码
        $decoded = self::base58Decode($address);

        if (strlen($decoded) !== 25) {
            throw new Exception('INVALID_DECODE_LENGTH');
        }

        // 3️⃣ 拆分
        $payload  = substr($decoded, 0, 21); // network + address
        $checksum = substr($decoded, 21, 4);

        // 4️⃣ 校验 checksum
        $hash = hash('sha256', hash('sha256', $payload, true), true);
        if (substr($hash, 0, 4) !== $checksum) {
            throw new Exception('INVALID_CHECKSUM');
        }

        // 5️⃣ 校验网络前缀（0x41 = TRON 主网）
        if (ord($payload[0]) !== 0x41) {
            throw new Exception('INVALID_NETWORK');
        }
    }

    /**
     * Base58 解码为二进制字符串
     */
    private static function base58Decode(string $input): string
    {
        $alphabet = self::BASE58_ALPHABET;
        $base = strlen($alphabet);

        $num = gmp_init(0);
        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $char = $input[$i];
            $index = strpos($alphabet, $char);
            if ($index === false) {
                throw new Exception('INVALID_BASE58_CHAR');
            }
            $num = gmp_add(gmp_mul($num, $base), $index);
        }

        $hex = gmp_strval($num, 16);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        $bin = hex2bin($hex);

        // 处理前导 0（Base58 的 1）
        $leadingZeroes = 0;
        for ($i = 0; $i < strlen($input) && $input[$i] === '1'; $i++) {
            $leadingZeroes++;
        }

        return str_repeat("\x00", $leadingZeroes) . $bin;
    }
}

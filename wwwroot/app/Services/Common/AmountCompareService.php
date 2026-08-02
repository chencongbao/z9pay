<?php

namespace App\Services\Common;

class AmountCompareService
{
    public function same($left, $right, int $scale = 2): bool
    {
        return $this->compare($left, $right, $scale) === 0;
    }

    public function lessThan($left, $right, int $scale = 2): bool
    {
        return $this->compare($left, $right, $scale) < 0;
    }

    public function greaterThan($left, $right, int $scale = 2): bool
    {
        return $this->compare($left, $right, $scale) > 0;
    }

    public function compare($left, $right, int $scale = 2): int
    {
        if (function_exists('bccomp')) {
            return bccomp((string)$left, (string)$right, $scale);
        }

        return $this->normalize($left, $scale) <=> $this->normalize($right, $scale);
    }

    private function normalize($amount, int $scale): string
    {
        return number_format((float)$amount, $scale, '.', '');
    }
}

<?php

namespace App\Rules;

use DateTime;
use Illuminate\Contracts\Validation\Rule;

class TransferOrderNumber implements Rule
{
    /**
     * Validate system transfer order number:
     * T + yyyyMMddHHmmss + 5 digit random number + positive ordernumber id.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        $value = (string)$value;
        if (!preg_match('/^T(\d{14})(\d{5})([1-9]\d*)$/', $value, $matches)) {
            return false;
        }

        $date = DateTime::createFromFormat('YmdHis', $matches[1]);

        return $date && $date->format('YmdHis') === $matches[1];
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return '代付系统订单号格式错误';
    }
}

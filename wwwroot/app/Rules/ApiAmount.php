<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ApiAmount implements Rule
{
    private $min;
    private $max;
    private $message = 'api.amount.numeric';

    public function __construct($min = '0.01', $max = '9999999999')
    {
        $this->min = (string)$min;
        $this->max = (string)$max;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!is_string($value) && !is_numeric($value)) {
            $this->message = 'api.amount.numeric';
            return false;
        }

        $value = (string)$value;
        if (!is_numeric($value)) {
            $this->message = 'api.amount.numeric';
            return false;
        }

        if (!preg_match('/^[1-9]\d{0,9}(\.\d{1,2})?$|^0\.\d{1,2}$/', $value)) {
            $this->message = 'api.amount.regex';
            return false;
        }

        if ($this->compare($value, $this->min) < 0) {
            $this->message = 'api.amount.min';
            return false;
        }

        if ($this->compare($value, $this->max) > 0) {
            $this->message = 'api.amount.max';
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }

    private function compare(string $left, string $right): int
    {
        if (function_exists('bccomp')) {
            return bccomp($left, $right, 2);
        }

        return (float)$left <=> (float)$right;
    }
}

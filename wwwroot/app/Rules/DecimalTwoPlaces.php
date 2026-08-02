<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class DecimalTwoPlaces implements Rule
{

    private $number = 2;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($number = 2)
    {
        $this->number = $number;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return is_numeric($value) && floor($value) == $value || (strpos((string)$value, '.') !== false && strlen(substr(strrchr($value, '.'), 1)) <= $this->number);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return '最多只能保留'.$this->number.'位小数';
    }
}

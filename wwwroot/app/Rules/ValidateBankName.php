<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidateBankName implements Rule
{
    private $bankCode;

    private $message = "请填写银行名称";

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($bank_code = '')
    {
        $this->bankCode = $bank_code;
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
        if($this->bankCode == 'OB' && empty($value)){
            $this->message = "请填写银行名称";
            return;
        }
        if(strlen($value) > 90){
            $this->message = "银行名称长度不能超过90";
            return;
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
}

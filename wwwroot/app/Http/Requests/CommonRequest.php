<?php

namespace App\Http\Requests;

use App\Traits\ResponseTraits;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\App;

class CommonRequest extends FormRequest
{

    use ResponseTraits;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function formatErrors(Validator $validator)
    {
        $this->result['code'] = -9999;
        $this->result['message'] = trans($validator->errors()->first());
        if(!App::isLocale('zh_CN')){
            $this->result['zh_message'] = trans($validator->errors()->first(),[],'zh_CN');
        }
        return $this->result;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($this->formatErrors($validator)));
    }

    protected function merchantUserId()
    {
        return $this->attributes->get('merchant_user_id');
    }
}

<?php

namespace App\Http\Requests\Api\V2;

use App\Http\Requests\CommonRequest;

class UploadImageRequest extends CommonRequest
{


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'file' => 'required|file|max:4096|mimes:jpeg,png'
        ];
    }

    public function messages()
    {
        return [
            'file.required' => '请上传图片',
            'file.file' => '请上传图片',
            'file.max' => '图片不能超过4M',
            'file.mimes' => "上传图片类型为png,jpg",
        ];
    }
}

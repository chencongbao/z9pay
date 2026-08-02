<?php

namespace App\Traits;

use Illuminate\Support\Facades\App;

trait ResponseTraits
{
    public $result = [
        'code' => -9999,
        'message' => '',
        'data' => null,
        'errorcode' => 0
    ];

    public $data = [];

    public $pageSize = 10;


    /**
     * 返回成功
     *
     * @param string $message
     * @param array $data
     * @return \Illuminate\Http\JsonResponse
     */
    public function success($message = '', $data = [])
    {
        $this->result['code'] = 200;
        $this->result['message'] = empty($message) ? "ok" : $message;
        if (!empty($data)) $this->result['data'] = $data;
        return response()->json($this->result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function getSuccess($message = '', $data = [])
    {
        $this->result['code'] = 200;
        $this->result['message'] = empty($message) ? "ok" : $message;
        if (!empty($data)) $this->result['data'] = $data;
        return $this->result;
    }


    /**
     * 返回失败
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function error($message = "", $zh_message = "",$errorcode = 10001)
    {
        $this->result['message'] = empty($message) ? "fail" : $message;
        $this->result['errorcode'] = $errorcode;
        if (!empty($zh_message) && !App::isLocale('zh_CN')) {
            $this->result['zh_message'] = $zh_message;
        }
        return response()->json($this->result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function getError($message = "")
    {
        $this->result['message'] = empty($message) ? "fail" : $message;
        return $this->result;
    }


    /**
     * 自定义返回
     *
     * @param int $status
     * @param string $message
     * @param array $data
     * @return \Illuminate\Http\JsonResponse
     */
    public function result($status = -9999, $message = "", $data = [])
    {
        $this->result['code'] = $status;
        $this->result['message'] = empty($message) ? '未知错误' : $message;
        if (!empty($data)) $this->result['data'] = $data;
        return response()->json($this->result);
    }


}

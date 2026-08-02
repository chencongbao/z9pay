<?php

namespace App\Traits;

use App\Services\Enums\ErrorCodeEnum;

trait ServiceResponseTrait
{
    protected function success(array $data, string $message = 'OK'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    protected function fail(string $message, string $zhMessage = '', int $errorCode = ErrorCodeEnum::COMMON_ERROR): array
    {
        return [
            'success' => false,
            'message' => $message,
            'zh_message' => $zhMessage,
            'error_code' => $errorCode,
        ];
    }

    protected function logSuccess(string $message, array $data): array
    {
        return [
            'code' => 200,
            'message' => $message,
            'data' => $data,
            'errorcode' => 0,
        ];
    }

    protected function logError(string $message): array
    {
        return [
            'code' => -9999,
            'message' => $message,
            'data' => null,
            'errorcode' => 0,
        ];
    }
}

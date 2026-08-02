<?php

namespace App\Traits;

use App\Services\Enums\ErrorCodeEnum;

trait ApiServiceResultResponseTrait
{
    protected function serviceResult(array $result)
    {
        if (empty($result['success'])) {
            return $this->error(
                $result['message'] ?? '',
                $result['zh_message'] ?? '',
                $result['error_code'] ?? ErrorCodeEnum::COMMON_ERROR
            );
        }

        return $this->success($result['message'] ?? 'OK', $result['data'] ?? []);
    }
}

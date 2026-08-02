<?php

return [
    'enabled' => env('TRANSFER_SUCCESS_RECEIPT_ENABLED', true),
    'chrome_binary' => env('TRANSFER_RECEIPT_CHROME_BINARY', ''),
    'timeout' => env('TRANSFER_RECEIPT_TIMEOUT', 30),
];

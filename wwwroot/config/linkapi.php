<?php

return [
    'api_key' => env('LINKAPI_API_KEY', 'sk-keJZhALrJUsiQyx5HuhfrdGsl8TjKV0q0lvcy2Ayfag3jOUG'),
    'base_uri' => env('LINKAPI_BASE_URI', 'https://api.linkapi.ai/v1'),
    'model' => env('LINKAPI_MODEL', 'gpt-4o-mini'),
    'request_timeout' => env('LINKAPI_REQUEST_TIMEOUT', 30),
];

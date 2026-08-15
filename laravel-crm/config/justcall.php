<?php

return [
    'enabled' => (bool) env('JUSTCALL_ENABLED', false),
    'base_url' => env('JUSTCALL_BASE_URL', 'https://api.justcall.io'),
    'dialer_url' => env('JUSTCALL_DIALER_URL', 'https://app.justcall.io/dialer'),
    'auth_mode' => env('JUSTCALL_AUTH_MODE', 'basic'),
    'api_key' => env('JUSTCALL_API_KEY', ''),
    'api_secret' => env('JUSTCALL_API_SECRET', ''),
    'webhook_secret' => env('JUSTCALL_WEBHOOK_SECRET', ''),
    'test_endpoint' => env('JUSTCALL_TEST_ENDPOINT', '/v2.1/users'),
    'webhook_replay_tolerance' => (int) env('JUSTCALL_WEBHOOK_REPLAY_TOLERANCE', 300),
    'max_webhook_payload_bytes' => (int) env('JUSTCALL_MAX_WEBHOOK_PAYLOAD_BYTES', 262144),
];

<?php

return [
    'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized' => env('MIDTRANS_IS_SANITIZED', true),
    'is_3ds' => env('MIDTRANS_IS_3DS', true),
    
    // Callback URLs
    'finish_url' => env('APP_URL') . '/api/midtrans/finish',
    'unfinish_url' => env('APP_URL') . '/api/midtrans/unfinish',
    'error_url' => env('APP_URL') . '/api/midtrans/error',
    'notification_url' => env('APP_URL') . '/api/midtrans/notification',
];
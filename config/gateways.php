<?php

return [
    'default' => env('SMS_DRIVER', 'log'),
    'from' => env('SMS_FROM', ''),

    // SMS Gateways
    'drivers' => [
        'log' => [
            'driver' => 'log',
            'channel' => 'single',
        ],
        
        'twilio' => [
            'driver' => 'twilio',
            'sid' => env('TWILIO_SID', ''),
            'token' => env('TWILIO_AUTH_TOKEN', ''),
            'from' => env('TWILIO_FROM', ''),
        ],

        'sprint' => [
            'driver' => 'sprint',
            'base_url' => env('SPRINT_SMS_BASE_URL', 'https://api.tanzaniasms.co.tz'),
            'api_id' => env('SPRINT_SMS_API_ID', ''),
            'api_password' => env('SPRINT_SMS_API_PASSWORD', ''),
            'sender_id' => env('SPRINT_SMS_SENDER_ID', env('SMS_FROM', 'PATOO')),
            'sms_type' => env('SPRINT_SMS_TYPE', 'T'),
            'encoding' => env('SPRINT_SMS_ENCODING', 'T'),
            'uid' => env('SPRINT_SMS_UID', ''),
            'callback_url' => env('SPRINT_SMS_CALLBACK_URL', ''),
            'validity_period_seconds' => env('SPRINT_SMS_VALIDITY_SECONDS', 0),
            'pe_id' => env('SPRINT_SMS_PE_ID', ''),
            'template_id' => env('SPRINT_SMS_TEMPLATE_ID', ''),
            'timeout_seconds' => env('SPRINT_SMS_TIMEOUT_SECONDS', 30),
        ],

        'beem' => [
            'driver' => 'beem',
            'base_url' => env('BEEM_BASE_URL', 'https://apigw.beem.io/v1'),
            'api_key' => env('BEEM_API_KEY', ''),
            'secret_key' => env('BEEM_SECRET_KEY', ''),
            'sender_id' => env('SMS_FROM', 'AROOS'),
        ],

        'africastalking' => [
            'driver' => 'africastalking',
            'username' => env('AT_USERNAME', ''),
            'api_key' => env('AT_API_KEY', ''),
        ],
    ],

    // WhatsApp Configuration
    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'driver' => env('WHATSAPP_DRIVER', 'twilio'), // twilio, clickpesa, or other
        'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
        'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),
        'api_token' => env('WHATSAPP_API_TOKEN', ''),
    ],

    // Mobile Money / Payment Gateways
    'mobile_money' => [
        'enabled' => env('MOBILE_MONEY_ENABLED', false),
        'default_gateway' => env('MOBILE_MONEY_GATEWAY', 'clickpesa'),

        'clickpesa' => [
            'enabled' => env('CLICKPESA_ENABLED', false),
            'client_id' => env('CLICKPESA_CLIENT_ID', ''),
            'api_key' => env('CLICKPESA_API_KEY', ''),
            'checksum_key' => env('CLICKPESA_CHECKSUM_KEY', ''),
            'enforce_checksum' => env('CLICKPESA_ENFORCE_CHECKSUM', true),
            'environment' => env('CLICKPESA_ENV', 'sandbox'),
            'base_url' => env('CLICKPESA_BASE_URL', 'https://api.clickpesa.com/third-parties'),
            'callback_url' => env('CLICKPESA_CALLBACK_URL', ''),
            'timeout_seconds' => env('CLICKPESA_TIMEOUT_SECONDS', 30),
        ],

        'selcom' => [
            'enabled' => env('SELCOM_ENABLED', false),
            'api_key' => env('SELCOM_API_KEY', ''),
            'api_secret' => env('SELCOM_API_SECRET', ''),
            'vendor' => env('SELCOM_VENDOR', ''),
            'environment' => env('SELCOM_ENV', 'sandbox'),
            'base_url' => env('SELCOM_BASE_URL', 'https://apigw.selcommobile.com/v1'),
            'callback_url' => env('SELCOM_CALLBACK_URL', ''),
            'timeout_seconds' => env('SELCOM_TIMEOUT_SECONDS', 30),
        ],

        'flutterwave' => [
            'enabled' => env('FLUTTERWAVE_ENABLED', false),
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY', ''),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY', ''),
            'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY', ''),
            'environment' => env('FLUTTERWAVE_ENV', 'sandbox'),
            'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
            'callback_url' => env('FLUTTERWAVE_CALLBACK_URL', ''),
        ],
    ],
];

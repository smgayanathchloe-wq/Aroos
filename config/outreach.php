<?php

/*
| Platform-wide fallbacks for AI pledge outreach. A wedding's own encrypted
| `outreach_secrets` win over these, so one platform account can serve every
| wedding and a wedding with its own accounts needs no code change.
*/

return [
    'secrets' => [
        'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
        'openai_api_key' => env('OPENAI_API_KEY'),
        'azure_openai_api_key' => env('AZURE_OPENAI_API_KEY'),
        'self_hosted_api_key' => env('OUTREACH_LLM_API_KEY'),
        'whatsapp_token' => env('WHATSAPP_API_TOKEN'),
        'whatsapp_app_secret' => env('WHATSAPP_APP_SECRET'),
        'twilio_sid' => env('TWILIO_SID'),
        'twilio_token' => env('TWILIO_AUTH_TOKEN'),
        'tts_api_key' => env('OUTREACH_TTS_API_KEY'),
        // ClickPesa: a wedding can collect into its own ClickPesa account.
        'clickpesa_client_id' => env('CLICKPESA_CLIENT_ID'),
        'clickpesa_api_key' => env('CLICKPESA_API_KEY'),
        'clickpesa_checksum_key' => env('CLICKPESA_CHECKSUM_KEY'),
    ],

    // Shared by the single Meta webhook URL for its GET verification handshake.
    'whatsapp_verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

    'clickpesa_base_url' => env('CLICKPESA_BASE_URL', 'https://api.clickpesa.com/third-parties'),
];

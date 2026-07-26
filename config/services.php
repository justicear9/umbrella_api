<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'web_search_model' => env('OPENAI_WEB_SEARCH_MODEL', 'gpt-4o'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'tts_model' => env('OPENAI_TTS_MODEL', 'gpt-4o-mini-tts'),
        'temperature' => (float) env('OPENAI_TEMPERATURE', 0.3),
        'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 2000),
    ],

    /*
    | National Chat @comrade live web verification (not used by Ask).
    | Domains must omit scheme; OpenAI web_search allowed_domains includes subdomains.
    */
    'chat_web_verify' => [
        'enabled' => filter_var(env('CHAT_WEB_VERIFY_ENABLED', true), FILTER_VALIDATE_BOOL),
        'allowed_domains' => [
            // JoyNews
            'myjoyonline.com',
            'joyonline.com',
            // CitiFM / Citi Newsroom
            'citinewsroom.com',
            // GTV / GBC
            'gbcghanaonline.com',
            'gtvghana.com',
            // TV3 / 3News (+ Three FM Media General family coverage)
            '3news.com',
            // International
            'bbc.com',
            'bbc.co.uk',
            'cnn.com',
            'dw.com',
        ],
        'outlet_labels' => [
            'myjoyonline.com' => 'JoyNews',
            'joyonline.com' => 'JoyNews',
            'citinewsroom.com' => 'CitiFM',
            'gbcghanaonline.com' => 'GBC',
            'gtvghana.com' => 'GTV',
            '3news.com' => 'TV3',
            'bbc.com' => 'BBC',
            'bbc.co.uk' => 'BBC',
            'cnn.com' => 'CNN',
            'dw.com' => 'DW',
        ],
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'tts_model' => env('GEMINI_TTS_MODEL', 'gemini-2.5-flash-preview-tts'),
        'tts_voice_preset' => env('GEMINI_TTS_VOICE_PRESET', 'ghanaian'),
    ],

];

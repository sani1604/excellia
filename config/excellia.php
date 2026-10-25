<?php

return [
    'school_name' => env('EXCELLIA_SCHOOL_NAME', 'Excellia School'),
    'default_timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),
    'academic_year_default' => env('EXCELLIA_ACADEMIC_YEAR', '2026-27'),

    /*
    |--------------------------------------------------------------------------
    | Google Workspace SSO Authentication
    |--------------------------------------------------------------------------
    */
    'sso' => [
        'enabled' => (bool) env('GOOGLE_SSO_ENABLED', true),
        'google_client_id' => env('GOOGLE_SSO_CLIENT_ID', env('GOOGLE_OAUTH_CLIENT_ID')),
        'google_client_secret' => env('GOOGLE_SSO_CLIENT_SECRET', env('GOOGLE_OAUTH_CLIENT_SECRET')),
        'google_redirect_uri' => env('GOOGLE_SSO_REDIRECT_URI'),
        'allowed_hosted_domain' => env('GOOGLE_SSO_HOSTED_DOMAIN', 'excelliaschool.edu.in'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Drive Workspace Integration
    |--------------------------------------------------------------------------
    */
    'drive' => [
        'mode' => env('GOOGLE_DRIVE_MODE', 'workspace_sandbox'), // workspace_sandbox | google_api
        'root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER_ID', '1ExcelliaRoot2026_27AcademicSharing'),
        'root_folder_name' => env('GOOGLE_DRIVE_ROOT_FOLDER_NAME', 'EXCELLIA SCHOOL – ACADEMIC SHARING – 2026–27'),
        'service_account_email' => env('GOOGLE_SERVICE_ACCOUNT_EMAIL'),
        'service_account_private_key' => env('GOOGLE_SERVICE_ACCOUNT_PRIVATE_KEY'),
        'impersonate_user_email' => env('GOOGLE_WORKSPACE_IMPERSONATE_EMAIL'),
        'oauth_client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'oauth_refresh_token' => env('GOOGLE_OAUTH_REFRESH_TOKEN'),
        'simulate_outage' => (bool) env('GOOGLE_DRIVE_SIMULATE_OUTAGE', false),
        'local_private_disk' => 'local',
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Notification Gateway (Whatify API / Meta Cloud / Webhook / Sandbox)
    |--------------------------------------------------------------------------
    | Whatify Docs: https://whatify.in/api-docs
    | External Base URL: https://whatify.in/api/v1/external
    | Header: X-API-Key: wfy_your_api_key_here
    */
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'sandbox'), // whatify | meta_cloud | webhook | sandbox
        'whatify_base_url' => env('WHATIFY_BASE_URL', 'https://whatify.in/api/v1/external'),
        'whatify_api_key' => env('WHATIFY_API_KEY'),
        'whatify_account_id' => env('WHATIFY_WHATSAPP_ACCOUNT_ID'),
        'whatify_mode' => env('WHATIFY_MESSAGE_MODE', 'text'), // text | template
        'whatify_template_name' => env('WHATIFY_TEMPLATE_NAME'),
        'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '91'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenRouter AI Assistant (Super Admin Controlled)
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'enabled' => (bool) env('OPENROUTER_ENABLED', false),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
        'allow_teachers' => (bool) env('OPENROUTER_ALLOW_TEACHERS', true),
        'allow_coordinators' => (bool) env('OPENROUTER_ALLOW_COORDINATORS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Security Controls
    |--------------------------------------------------------------------------
    */
    'security' => [
        'blocked_extensions' => [
            'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'pif', 'vbs', 'vbe',
            'js', 'jse', 'wsf', 'wsh', 'ps1', 'psm1', 'sh', 'bash', 'zsh',
            'php', 'phtml', 'phar', 'py', 'rb', 'pl', 'cgi', 'jar', 'war',
            'dll', 'so', 'dylib', 'apk', 'app', 'hta', 'html', 'htm', 'svg',
        ],
        'blocked_mime_prefixes' => [
            'application/x-msdownload',
            'application/x-executable',
            'application/x-dosexec',
            'application/x-sh',
            'application/x-httpd-php',
            'text/x-php',
            'text/x-shellscript',
            'application/javascript',
            'text/javascript',
            'text/html',
            'image/svg+xml',
        ],
        'allowed_mime_map' => [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/CDFV2', 'application/octet-stream'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
            ],
            'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/octet-stream'],
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
            ],
            'ppt' => ['application/vnd.ms-powerpoint', 'application/CDFV2', 'application/octet-stream'],
            'pptx' => [
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/zip',
            ],
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow & Overdue Defaults
    |--------------------------------------------------------------------------
    */
    'workflow' => [
        'due_soon_days' => 3,
        'default_review_sla_hours' => 48,
        'max_notification_retries' => 3,
    ],
];

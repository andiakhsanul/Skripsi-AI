<?php

return [
    'drive' => [
        'oauth_client_file' => env('GOOGLE_OAUTH_CLIENT_FILE', storage_path('app/google/oauth-client.json')),
        'oauth_token_file' => env('GOOGLE_OAUTH_TOKEN_FILE', storage_path('app/google/oauth-token.json')),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'application_name' => env('GOOGLE_DRIVE_APP_NAME', 'SPK-KIPK Laravel'),
    ],
];

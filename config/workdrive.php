<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WorkDrive Configuration
    |--------------------------------------------------------------------------
    |
    | General configuration for WorkDrive system
    |
    */

    'storage' => [
        'disk' => env('WORKDRIVE_DISK', 'local'),
        'max_file_size' => env('WORKDRIVE_MAX_FILE_SIZE', 10737418240), // 10GB
        'max_upload_size' => env('WORKDRIVE_MAX_UPLOAD_SIZE', 5368709120), // 5GB
    ],

    'trash' => [
        'retention_days' => env('WORKDRIVE_TRASH_RETENTION', 30),
    ],

    'sharing' => [
        'enable_public_links' => env('WORKDRIVE_ENABLE_PUBLIC_LINKS', false),
        'default_expiry_days' => env('WORKDRIVE_DEFAULT_EXPIRY', 30),
    ],

    'features' => [
        'versioning' => true,
        'collaborative_editing' => true,
        'search' => true,
        'preview' => true,
    ],
];

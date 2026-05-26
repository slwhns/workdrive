<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OnlyOffice Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OnlyOffice Document Editor integration
    |
    */

    'url' => env('ONLYOFFICE_URL', 'https://onlyoffice.example.com'),

    'secret' => env('ONLYOFFICE_SECRET', ''),

    'jwt_enabled' => env('ONLYOFFICE_JWT_ENABLED', true),

    'callback_url' => env('ONLYOFFICE_CALLBACK_URL', '/api/editor/callback'),

    /*
    |--------------------------------------------------------------------------
    | Supported file types for OnlyOffice
    |--------------------------------------------------------------------------
    */

    'supported_formats' => [
        'documents' => ['docx', 'doc', 'odt', 'rtf', 'txt', 'html', 'htm', 'mhtml', 'pdf'],
        'spreadsheets' => ['xlsx', 'xls', 'ods', 'csv'],
        'presentations' => ['pptx', 'ppt', 'odp'],
    ],
];

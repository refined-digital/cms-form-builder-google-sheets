<?php
return [
    // spreadsheet to append rows to
    'spreadsheet_id' => env('GOOGLE_SHEETS_ID'),

    // simple API key (used for read calls that don't require oauth)
    'api_key' => env('GOOGLE_API_KEY'),

    // path to the service-account json, relative to storage/app, used for writes
    'auth_config' => env('GOOGLE_AUTH_CONFIG'),

    // prepend a UTC timestamp column to every appended row
    'timestamp' => true,
];

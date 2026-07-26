<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The storage disk to use for storing generated report files.
    |
    */
    'disk' => env('REPORT_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | The path within the storage disk where report files will be saved.
    |
    */
    'path' => env('REPORT_PATH', 'reports'),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Maximum number of records per chunk when generating large reports.
    |
    */
    'chunk_size' => (int) env('REPORT_CHUNK_SIZE', 500),
];

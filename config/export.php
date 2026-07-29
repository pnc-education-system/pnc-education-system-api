<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | Settings controlling export performance, chunk sizes, and async thresholds.
    |
    | R5 Requirement: Indexed/chunked queries must complete ≤10s for 5,000 records.
    | When the estimated row count exceeds 'async_threshold', the export is
    | processed via a queued job and the user is notified when ready.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Sync Threshold
    |--------------------------------------------------------------------------
    |
    | Maximum number of rows that can be exported synchronously (≤10s).
    | If the query would return more than this, the export is queued.
    | Set to 0 to always queue, or a high number to always export sync.
    |
    */
    'sync_threshold' => env('EXPORT_SYNC_THRESHOLD', 5000),

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | Number of records to fetch per database query when streaming export data.
    | Larger chunks = fewer queries but more memory per chunk.
    | Default: 500 (balanced for 5,000-record datasets)
    |
    */
    'chunk_size' => (int) env('EXPORT_CHUNK_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The storage disk to use for storing generated export files (async exports).
    |
    */
    'disk' => env('EXPORT_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | The path within the storage disk where export files will be saved.
    |
    */
    'path' => env('EXPORT_PATH', 'exports'),

    /*
    |--------------------------------------------------------------------------
    | Retention Days
    |--------------------------------------------------------------------------
    |
    | Number of days to keep generated export files before cleanup.
    |
    */
    'retention_days' => (int) env('EXPORT_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | PDF Export Settings
    |--------------------------------------------------------------------------
    |
    | Settings for PDF report generation.
    |
    */
    'pdf' => [
        'page_size' => env('EXPORT_PDF_PAGE_SIZE', 'A4'),
        'orientation' => env('EXPORT_PDF_ORIENTATION', 'landscape'),
    ],
];

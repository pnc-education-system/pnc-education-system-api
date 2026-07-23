<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Photo Storage Disk
    |--------------------------------------------------------------------------
    |
    | The storage disk to use for storing uploaded photos.
    | Options: 'local', 'public', 's3', etc.
    |
    */
    'disk' => env('PHOTO_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Photo Storage Path
    |--------------------------------------------------------------------------
    |
    | The path within the storage disk where photos will be stored.
    |
    */
    'path' => env('PHOTO_PATH', 'photos'),

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | Maximum file size for uploaded photos in kilobytes.
    | Default: 5120 KB (5 MB)
    |
    */
    'max_file_size_kb' => env('PHOTO_MAX_FILE_SIZE_KB', 10240),
];

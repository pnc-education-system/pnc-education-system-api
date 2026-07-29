<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The storage disk to use for storing generated card PDFs.
    |
    */
    'disk' => env('CARD_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | The path within the storage disk where card PDFs will be saved.
    |
    */
    'path' => env('CARD_PATH', 'cards'),

    /*
    |--------------------------------------------------------------------------
    | Cards Per Page
    |--------------------------------------------------------------------------
    |
    | Number of student ID cards to render on a single A4 page.
    |
    */
    'per_page' => (int) env('CARD_CARDS_PER_PAGE', 8),

    /*
    |--------------------------------------------------------------------------
    | Page Size
    |--------------------------------------------------------------------------
    |
    | Page size for the PDF. Supported: A4
    |
    */
    'page_size' => env('CARD_PAGE_SIZE', 'A4'),

    /*
    |--------------------------------------------------------------------------
    | QR Token Length
    |--------------------------------------------------------------------------
    |
    | Length of the random QR token generated for each card.
    |
    */
    'qr_token_length' => 32,
];

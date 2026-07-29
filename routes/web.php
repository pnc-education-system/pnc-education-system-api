<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Catch-all route to serve storage files through Laravel middleware stack.
// This ensures CORS headers (Access-Control-Allow-Origin) are added by the
// HandleCors middleware for requests to /storage/* paths.
Route::get('storage/{path}', function ($path) {
    $disk = Storage::disk('public');

    if (!$disk->exists($path)) {
        abort(404);
    }

    $absolutePath = $disk->path($path);
    $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';

    return response()->file($absolutePath, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');

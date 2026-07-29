<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    Barryvdh\DomPDF\ServiceProvider::class,
    SimpleSoftwareIO\QrCode\QrCodeServiceProvider::class,
];

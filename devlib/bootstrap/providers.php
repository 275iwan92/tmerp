<?php

use App\Providers\AppServiceProvider;
use MockupParts\MockupPartsServiceProvider;

return [
    AppServiceProvider::class,
    MockupPartsServiceProvider::class,   // objek form khusus (x-mp::*) yang ikut ditampilkan di katalog (user 2026-10-05)
];

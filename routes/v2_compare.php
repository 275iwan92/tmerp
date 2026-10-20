<?php

use App\Support\V2\Compare;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Layar pembanding Special Forms (admin saja): data contoh statis, tanpa baca / tulis basis data. Dimuat di dalam grup v2 routes/v2.php.
Route::get('/compare/{slug}', function (string $slug) {
    abort_unless(Auth::user()?->is_admin, 403);
    $screen = Compare::screens()[$slug] ?? abort(404);

    return view('v2.compare', ['slug' => $slug, 'title' => $screen[0]]);
})->name('compare');

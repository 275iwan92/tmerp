<?php

use App\Support\Flows;
use App\Support\Menu;
use App\Support\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Situs Mockup ERP tanpa database: sesi file + akun dari config/devlib.php. Layar = susunan objek DevLib.

Route::get('/login', fn () => view('login', [
    'variant' => config('devlib.login_variant'),
    'error' => session('login_error'),
    'username' => old('login', ''),
]))->name('login');

Route::post('/login', function (Request $r) {
    $login = trim((string) $r->input('login'));
    $password = (string) $r->input('password');
    if ($login === '' || $password === '') {
        return back()->withInput(['login' => $login])->with('login_error', 'empty');
    }
    $user = collect(config('devlib.users'))->first(fn ($u) => strcasecmp($u['login'], $login) === 0 && hash_equals($u['password'], $password));
    if (! $user) {
        return back()->withInput(['login' => $login])->with('login_error', 'wrong');
    }
    $r->session()->regenerate();
    $r->session()->put('devlib_user', collect($user)->except('password')->all());

    return redirect()->intended(route('home'));
})->name('login.submit');

Route::post('/logout', function (Request $r) {
    $r->session()->invalidate();
    $r->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::middleware('devlib.auth')->group(function () {
    Route::get('/', fn () => view('home', ['modules' => Menu::shell(), 'user' => session('devlib_user')]))->name('home');

    // Satu layar mockup. Belum dibangun -> kartu info (acuan tmerp, mockup asli, status).
    Route::get('/s/{slug}', function (string $slug) {
        $screen = Menu::screen($slug) ?? abort(404);
        $file = resource_path("views/screens/{$slug}.blade.php");

        return view('screen', ['screen' => $screen, 'built' => is_file($file), 'code' => is_file($file) ? file_get_contents($file) : '']);
    })->name('screen');

    Route::get('/flow/{module}', fn (string $module) => view('flow', ['flow' => Flows::get($module) ?? abort(404)]))->name('flow');

    // Data Dictionary: khusus akun developer (bukan untuk user bisnis).
    Route::get('/dictionary', function () {
        abort_unless(session('devlib_user.developer'), 403);

        return view('dictionary', ['stats' => Schema::stats(), 'columns' => Schema::columns(), 'tables' => Schema::tables(),
            'duplicates' => Schema::duplicates(), 'copies' => Schema::copies(), 'changes' => Schema::changes()]);
    })->name('dictionary');

    // Mockup HTML/gambar asli (docs/mockups), dibuka dari kartu info layar.
    Route::get('/berkas/{file}', function (string $file) {
        $root = realpath(config('devlib.mockup_path'));
        $real = realpath($root.DIRECTORY_SEPARATOR.$file);
        abort_unless($real && $root && is_file($real) && str_starts_with($real, $root.DIRECTORY_SEPARATOR), 404);

        return response()->file($real);
    })->where('file', '.*')->name('mockup.file');
});

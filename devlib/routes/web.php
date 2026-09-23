<?php

use App\Support\Catalog;
use Devlib\Components\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Situs DevLib tanpa database: sesi file + akun dari config/devlib.php.

Route::get('/login', fn (Request $r) => view('login', [
    'model' => config('devlib.login_model'),
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
    Route::get('/', fn () => view('home', ['modules' => Catalog::modules(), 'user' => session('devlib_user')]))->name('home');

    // Halaman banding: kiri mockup asli, kanan komponen library.
    Route::get('/komponen/{file}', function (string $file) {
        $mockup = Catalog::find($file) ?? abort(404);

        return view('compare', ['mockup' => $mockup, 'component' => Catalog::component($file), 'models' => Login::MODELS]);
    })->where('file', '.*')->name('compare');

    // Pratinjau komponen (dimuat di panel kanan halaman banding).
    Route::get('/pratinjau/login', fn (Request $r) => view('preview.login', ['model' => $r->query('model', 'split')]))->name('preview.login');
    Route::get('/pratinjau/shell', fn () => view('preview.shell', ['modules' => Catalog::modules(), 'user' => session('devlib_user')]))->name('preview.shell');

    Route::get('/berkas/{file}', function (string $file) {
        $path = Catalog::path($file);
        abort_unless($path, 404);

        return response()->file($path);
    })->where('file', '.*')->name('mockup.file');
});

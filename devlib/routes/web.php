<?php

use App\Support\Catalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Situs DevLib tanpa database: sesi file + akun dari config/devlib.php.

Route::get('/login', fn () => view('login', [
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

    // Halaman komponen: komponen hidup + ikon </> berisi kode pemakaiannya.
    Route::get('/lib/{slug}', function (Request $r, string $slug) {
        $page = Catalog::page($slug) ?? abort(404);

        return view('lib', [
            'page' => $page,
            'demo' => $slug,
            'code' => file_get_contents(resource_path("views/demos/{$slug}.blade.php")),
            'data' => [
                'model' => $r->query('model', config('devlib.login_model')),
                'modules' => Catalog::modules(),
                'user' => session('devlib_user'),
                'locations' => config('devlib.demo.locations'),
                'languages' => config('devlib.demo.languages'),
            ],
        ]);
    })->name('lib');

    // Mockup asli (untuk yang komponennya belum dibangun, dan tautan dari ikon </>).
    Route::get('/mockup/{file}', function (string $file) {
        $mockup = Catalog::find($file) ?? abort(404);

        return view('mockup', ['mockup' => $mockup]);
    })->where('file', '.*')->name('mockup');

    Route::get('/berkas/{file}', function (string $file) {
        $path = Catalog::path($file);
        abort_unless($path, 404);

        return response()->file($path);
    })->where('file', '.*')->name('mockup.file');
});

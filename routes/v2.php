<?php

use App\Models\Sdi\IqpInquiry;
use App\Services\Sales\InquiryDuplicator;
use App\Support\V2\AppMenu;
use App\Support\V2\Flows;
use App\Support\V2\Schema;
use App\Support\V2\Screens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Layar v2 (objek DevLib, Menu v2 tanpa top bar). Dimuat di dalam grup auth + force.password routes/web.php.
// mockup.test (APP_UI=v2) mendarat di sini; tmerp.test tetap v1 sampai go-live.
Route::prefix('v2')->name('v2.')->group(function () {
    // ?open=<kode menu> = tab yang dibuka saat mulai (mis. Direksi -> MGMT_EXEC), bila belum ada tab yang dipulihkan.
    Route::get('/', fn (Request $r) => view('v2.home', ['modules' => AppMenu::modules(Auth::user()), 'user' => AppMenu::user(Auth::user()),
        'open' => $r->query('open')]))->name('home');

    Route::post('/logout', function (Request $r) {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    // Satu item menu: layar v2 (target v2:slug), layar v1 tmerp (data sesuai .env situs), atau kartu "belum ada layar".
    Route::get('/item/{code}', function (string $code) {
        [$kind, $url, $item] = AppMenu::resolve($code);

        return $url ? redirect($url) : view('v2.missing', ['item' => $item]);
    })->name('item');

    Route::get('/s/{slug}', function (string $slug) {
        $screen = Screens::screen($slug) ?? abort(404);
        abort_unless(Auth::user()->canAccessModule($screen['module_code'] ?? 'SALES'), 403);
        $file = resource_path("views/v2/screens/{$slug}.blade.php");

        return view('v2.screen', ['screen' => $screen, 'built' => is_file($file), 'code' => is_file($file) ? file_get_contents($file) : '']);
    })->name('screen');

    // Duplicate inquiry (spec sama, customer lain) -> NEW DRAFT baru; logika = App\Services\Sales\InquiryDuplicator (sama dgn v1).
    Route::post('/inquiry/duplicate', function (Request $r) {
        abort_unless(Auth::user()->canAccessModule('SALES'), 403);
        $data = $r->validate([
            'source_id' => ['required', 'integer', 'exists:tm2sdi.iqp_inquiry,id_inquiry'],
            'customer_id' => ['required', 'integer', 'exists:tm2mst.cust_customer,id_customer'],
        ], [], ['customer_id' => 'Customer tujuan']);
        $source = IqpInquiry::findOrFail($data['source_id']);
        $new = InquiryDuplicator::duplicate($source, (int) $data['customer_id'], Auth::user()->login);

        return redirect()->route('v2.screen', 'inquiry')
            ->with('v2_flash', "Duplikat dibuat: {$new->inquiry_no} (Produk Baru, DRAFT) dari {$source->inquiry_no}.");
    })->name('inquiry.duplicate');

    Route::get('/flow/{module}', fn (string $module) => view('v2.flow', ['flow' => Flows::get($module) ?? abort(404)]))->name('flow');

    // Data Dictionary: khusus Administrator / grup DEVELOPER.
    Route::get('/dictionary', function () {
        abort_unless(AppMenu::user(Auth::user())['developer'], 403);

        return view('v2.dictionary', ['stats' => Schema::stats(), 'columns' => Schema::columns(), 'tables' => Schema::tables(),
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

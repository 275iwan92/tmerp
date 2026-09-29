<?php

use App\Console\Commands\DdtBaseline;
use App\Models\Sdi\IqpInquiry;
use App\Models\Sys\SysConstant;
use App\Services\Sales\InquiryDuplicator;
use App\Services\Sales\InquiryWriter;
use App\Support\V2\AppMenu;
use App\Support\V2\Flows;
use App\Support\V2\InquiryPayload;
use App\Support\V2\Schema;
use App\Support\V2\Screens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

    // Inquiry Baru v2 -> database situs (ddt* di mockup.test, tm2* live). Logika = App\Services\Sales\InquiryWriter (salinan persist*() v1).
    Route::prefix('inquiry')->name('inquiry.')->group(function () {
        $writer = fn () => new InquiryWriter(Auth::user()->login);
        $doc = function (int $id) {
            abort_unless(Auth::user()->canAccessModule('SALES'), 403);

            return IqpInquiry::findOrFail($id);
        };

        // Simpan / Confirm (action=confirm). Balasan JSON: url dokumen tersimpan untuk dimuat ulang.
        Route::post('/save', function (Request $r) use ($writer) {
            abort_unless(Auth::user()->canAccessModule('SALES'), 403);
            $old = $r->filled('id') ? IqpInquiry::findOrFail((int) $r->input('id')) : null;
            $r->validate(InquiryPayload::rules(), [], ['customer_id' => 'Customer', 'type_code' => 'Tipe Produk', 'product_draft_name' => 'Nama Produk']);
            $img = ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:'.(int) (SysConstant::number('INQUIRY_ARTWORK_MAX_MB', 10) * 1024)];
            $r->validate(['parts.*.artwork' => $img, 'parts.*.artwork_inner' => $img]);
            $inq = $writer()->save(InquiryPayload::from($r, $old), $old, InquiryPayload::artworks($r));
            $confirm = $r->input('action') === 'confirm';
            if ($confirm) {
                $writer()->confirm($inq);
            }

            return response()->json(['id' => $inq->id_inquiry, 'url' => route('v2.screen', 'inquiry-new').'?id='.$inq->id_inquiry,
                'message' => $confirm ? "Inkuiri {$inq->inquiry_no} dikonfirmasi — status SUBMITTED, form terkunci." : "Tersimpan sebagai {$inq->inquiry_no} ({$inq->refresh()->sub_state})."]);
        })->name('save');

        Route::post('/{id}/end', function (Request $r, int $id) use ($writer, $doc) {
            $inq = $doc($id);
            $data = $r->validate(['state' => ['required', 'in:CANCELLED,LOST'], 'reason' => ['required', 'string', 'max:255']], [], ['reason' => 'Alasan']);
            $data['state'] === 'CANCELLED' ? $writer()->cancel($inq, $data['reason']) : $writer()->markLost($inq, $data['reason']);

            return response()->json(['message' => $data['state'] === 'CANCELLED' ? "Inkuiri {$inq->inquiry_no} dibatalkan." : "Inkuiri {$inq->inquiry_no} ditandai kalah (LOST)."]);
        })->name('end');

        // Artwork boleh diganti setelah Confirm (user 2026-09-28): langsung tersimpan tanpa tombol Simpan.
        Route::post('/{id}/artwork', function (Request $r, int $id) use ($writer, $doc) {
            $inq = $doc($id);
            $r->validate(['part_id' => ['required', 'integer'], 'artwork' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.(int) (SysConstant::number('INQUIRY_ARTWORK_MAX_MB', 10) * 1024)]]);
            $part = $inq->parts()->findOrFail((int) $r->input('part_id'));
            $inner = $r->boolean('inner');
            $writer()->storeArtwork($part, $r->file('artwork'), $inner);

            return response()->json(['message' => 'Artwork '.($inner ? 'sisi dalam ' : '')."{$part->part_name} tersimpan."]);
        })->name('artwork');

        Route::post('/{id}/prodev', function (Request $r, int $id) use ($writer, $doc) {
            $inq = $doc($id);
            $part = $inq->parts()->findOrFail((int) $r->input('part_id'));
            $prodPart = $writer()->sendToProDev($inq, $part);

            return response()->json(['url' => route('v2.screen', 'prodev').'?part='.$prodPart.'&inquiry='.$inq->id_inquiry]);
        })->name('prodev');

        // Quick-add customer dari Inquiry (status DRAFT, review Finance/Credit tetap wajib).
        Route::post('/customer', function (Request $r) use ($writer) {
            abort_unless(Auth::user()->canAccessModule('SALES'), 403);
            $d = $r->validate(['name' => ['required', 'string', 'max:200'], 'type' => ['required', 'in:local,import'], 'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:150'], 'address' => ['nullable', 'string']], [], ['name' => 'Nama Customer']);
            $c = $writer()->quickCustomer($d + ['phone' => null, 'email' => null, 'address' => null]);

            return response()->json(['id' => $c->id_customer, 'label' => $c->customer_name, 'code' => $c->customer_code,
                'message' => "Customer baru \"{$c->customer_name}\" ({$c->customer_code}) dibuat — status DRAFT."]);
        })->name('customer');
    });

    Route::get('/flow/{module}', fn (string $module) => view('v2.flow', ['flow' => Flows::get($module) ?? abort(404)]))->name('flow');

    // Data Dictionary: khusus Administrator / grup DEVELOPER.
    Route::get('/dictionary', function () {
        abort_unless(AppMenu::user(Auth::user())['developer'], 403);

        return view('v2.dictionary', ['stats' => Schema::stats(), 'columns' => Schema::columns(), 'tables' => Schema::tables(),
            'duplicates' => Schema::duplicates(), 'copies' => Schema::copies(), 'changes' => Schema::changes()]);
    })->name('dictionary');

    // Data dummy: baseline & reset (user 2026-09-28). Developer saja; ddt:baseline menolak bila bukan database ddt*.
    Route::prefix('dummy')->name('dummy.')->group(function () {
        $guard = fn () => abort_unless(AppMenu::user(Auth::user())['developer'], 403);
        $run = function (string $action, string $ok) use ($guard) {
            $guard();
            set_time_limit(0);
            try {
                Artisan::call('ddt:baseline', ['action' => $action, '--force' => true]);
            } catch (Throwable $e) {
                return back()->with('v2_error', $e->getMessage());
            }

            return back()->with('v2_flash', $ok.' '.trim(collect(explode("\n", Artisan::output()))->filter()->last()));
        };
        Route::get('/', function () use ($guard) {
            $guard();

            return view('v2.dummy', ['manifest' => DdtBaseline::manifest()]);
        })->name('index');
        Route::post('/reset', fn () => $run('restore', 'Reset selesai.'))->name('reset');
        Route::post('/save', fn () => $run('save', 'Baseline disimpan.'))->name('save');
    });

    // Mockup HTML/gambar asli (docs/mockups), dibuka dari kartu info layar.
    Route::get('/berkas/{file}', function (string $file) {
        $root = realpath(config('devlib.mockup_path'));
        $real = realpath($root.DIRECTORY_SEPARATOR.$file);
        abort_unless($real && $root && is_file($real) && str_starts_with($real, $root.DIRECTORY_SEPARATOR), 404);

        return response()->file($real);
    })->where('file', '.*')->name('mockup.file');
});

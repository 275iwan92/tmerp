<?php

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvShortageRequest;
use App\Models\Inventory\InvSupplierClaim;
use App\Models\Pur\PvLine;
use App\Services\Inventory\QcAuthority;
use App\Services\Inventory\QcDecisionService;
use App\Services\Inventory\QcTraceService;
use App\Services\Pur\ShortageRequestService;
use App\Services\Pur\UrgentReceiptService;
use App\Support\V2\Grid\Inv\BatchLookup;
use Devlib\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Inventory > Batch & Quality (v2.01 jalur QC, URGENT, kurang kirim). Dimuat di dalam grup v2 routes/v2.php. Route tipis: aturan di
// App\Services\Inventory\Qc* dan App\Services\Pur\{UrgentReceiptService, ShortageRequestService}; hak tiap langkah di QcAuthority.
// Semua kegagalan aturan = InvalidArgumentException / RuntimeException bernama -> pesan di layar asal, tidak ada yang tersimpan setengah.
Route::prefix('inv-quality')->name('iq.')->group(function () {
    $fail = fn (string $slug, array $query, string $key, Throwable $e) => redirect(route('v2.screen', $slug).($query ? '?'.http_build_query($query) : ''))->withErrors([$key => $e->getMessage()]);
    $input = fn (Request $r, string $key) => ($v = $r->input($key)) === null || $v === '' ? null : (float) Locale::input($v);

    // Keputusan mutu satu batch (Conditional Stock): decision = RELEASED | REJECTED | BLOCKED | CONDITIONAL_USE.
    Route::post('/batch/{id}/decide', function (Request $r, int $id) use ($fail, $input) {
        $batch = InvBatch::query()->findOrFail($id);
        $back = ['id' => $id];
        // from=inv-batch: keputusan diambil dari tab Quality decisions di form Batch; selain itu dari Conditional Stock.
        $screen = $r->input('from') === 'inv-batch' ? 'inv-batch' : 'conditional-stock';
        if (! QcAuthority::canDecide(Auth::user())) {
            return $fail($screen, $back, 'decide', new RuntimeException(t('Only Quality Assurance or an operations manager can record the decision.')));
        }
        $d = $r->validate(['decision' => ['required', Rule::in(array_keys(QcDecisionService::DECISIONS))], 'decision_type' => ['required', Rule::in(array_keys(QcDecisionService::TYPES))],
            'reason' => ['nullable', 'string', 'max:255'], 'test_result' => ['nullable', 'string', 'max:255'], 'sample_size' => ['nullable', 'integer', 'min:0'], 'sample_defects' => ['nullable', 'integer', 'min:0']],
            [], ['decision' => t('Decision'), 'decision_type' => t('Inspection')]);
        try {
            QcDecisionService::record($batch, $d['decision_type'], $d['decision'], Auth::user()->login, ['reason' => $d['reason'] ?? null, 'test_result' => $d['test_result'] ?? null,
                'sample_size' => $d['sample_size'] ?? null, 'sample_defects' => $d['sample_defects'] ?? null, 'lot_qty' => $input($r, 'lot_qty')]);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $fail($screen, $back, 'decide', $e);
        }

        return redirect(route('v2.screen', $screen).'?id='.$id)->with('v2_flash', tf('Batch :no is now :status.', ['no' => raw($batch->batch_no), 'status' => t(InvBatch::STATUSES[$batch->fresh()->qc_status]['label'])]));
    })->whereNumber('id')->middleware('module:INV')->name('decide');

    // Simpan master batch dari form Batch (id kosong = batch baru). Aturan, nomor otomatis dan kunci kolom batch tersimpan ada di BatchLookup::save;
    // status TIDAK ikut disimpan di sini (hanya QcDecisionService).
    Route::post('/batch/save/{id?}', function (Request $r, ?int $id = null) {
        $batch = $id ? InvBatch::query()->findOrFail($id) : null;
        $lookup = new BatchLookup;
        // Certificate of Analysis (PDF / gambar, maks. 5 MB): disimpan di disk public dan jalurnya di inv_batch.coa_path setelah batch tersimpan.
        $r->validate(['coa' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']]);
        $row = ['id' => $id, 'stock_class' => $batch?->stock_class ?? $r->input('stock_class'), 'item_code' => $batch?->item_code ?? $r->input('item_code'), 'food_grade' => $r->boolean('food_grade'),
            'activate' => $batch ? (bool) $batch->activate : true] + $r->only(['supplier_id', 'supplier_lot', 'production_date', 'expiry_date', 'retest_date', 'certification', 'grain_direction', 'notes']);
        try {
            $lookup->save([$row]);
        } catch (ValidationException $e) {
            return redirect(route('v2.screen', 'inv-batch').($id ? '?id='.$id : '?new=1'))->withErrors(['batch' => $e->errors()['row'][0] ?? $e->getMessage()])->withInput();
        }
        $saved = (int) $lookup->lastSavedId();
        if ($file = $r->file('coa')) {
            $target = InvBatch::query()->findOrFail($saved);
            $target->forceFill(['coa_path' => $file->storeAs('batch/'.$target->batch_no, $file->getClientOriginalName(), 'public')])->save();
        }

        return redirect(route('v2.screen', 'inv-batch').'?id='.$saved)->with('v2_flash', t('Saved.'));
    })->whereNumber('id')->middleware('module:INV')->name('batch-save');

    Route::post('/claim/{id}', function (Request $r, int $id) use ($fail) {
        $claim = InvSupplierClaim::query()->findOrFail($id);
        $back = ['id' => $claim->batch_id];
        if (! QcAuthority::canDecide(Auth::user()) && ! QcAuthority::canFollowUp(Auth::user())) {
            return $fail('conditional-stock', $back, 'claim', new RuntimeException(t('You are not allowed to change a supplier claim.')));
        }
        $d = $r->validate(['status' => ['required', Rule::in(['OPEN', 'SENT', 'SETTLED', 'DROPPED'])], 'notes' => ['nullable', 'string', 'max:255']]);
        QcTraceService::setClaimStatus($claim, $d['status'], Auth::user()->login, $d['notes'] ?? null);

        return redirect(route('v2.screen', 'conditional-stock').'?id='.$claim->batch_id)->with('v2_flash', t('Saved.'));
    })->whereNumber('id')->middleware('module:INV')->name('claim');

    Route::post('/first-article', function (Request $r) use ($fail) {
        if (! QcAuthority::canDecide(Auth::user())) {
            return $fail('qc-first-article', ['new' => 1], 'first', new RuntimeException(t('Only Quality Assurance or an operations manager can record a first article.')));
        }
        $d = $r->validate(['wo_id' => ['required', 'integer'], 'batch_id' => ['required', 'integer'], 'result' => ['required', Rule::in(['PASS', 'FAIL'])], 'notes' => ['nullable', 'string', 'max:255']],
            [], ['wo_id' => t('Work Order'), 'batch_id' => t('Material Batch'), 'result' => t('Result')]);
        try {
            QcDecisionService::firstArticle((int) $d['wo_id'], InvBatch::query()->findOrFail((int) $d['batch_id']), $d['result'], Auth::user()->login, $d['notes'] ?? null);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $fail('qc-first-article', ['new' => 1], 'first', $e);
        }

        return redirect(route('v2.screen', 'qc-first-article'))->with('v2_flash', $d['result'] === 'PASS' ? t('First article passed — the batch is released.') : t('First article failed — the batch is rejected and its work orders are held.'));
    })->middleware('module:INV')->name('first-article');

    // Tanda URGENT, sertifikasi batch dan persetujuan pada satu baris penerimaan (bagian di Goods Receipt). step = mark | unmark | certify | approve.
    Route::post('/urgent/{pvId}', function (Request $r, int $pvId) use ($fail) {
        $back = ['id' => $pvId];
        $d = $r->validate(['pv_line_id' => ['required', 'integer'], 'step' => ['required', Rule::in(['mark', 'unmark', 'certify', 'approve'])], 'wo_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:255'], 'certification' => ['nullable', 'string', 'max:20']], [], ['pv_line_id' => t('Line')]);
        $line = PvLine::query()->where('pv_id', $pvId)->findOrFail((int) $d['pv_line_id']);
        $user = Auth::user();

        try {
            $done = match ($d['step']) {
                'mark' => [UrgentReceiptService::mark($line, (string) ($d['reason'] ?? ''), (int) ($d['wo_id'] ?? 0), $user), t('The line is marked URGENT.')],
                'unmark' => [UrgentReceiptService::unmark($line, $user), t('The URGENT mark is cleared.')],
                'certify' => [UrgentReceiptService::setCertification($line, (string) ($d['certification'] ?: 'NONE'), $r->boolean('food_grade'), $user->login), t('Saved.')],
                'approve' => [UrgentReceiptService::approve($line, $user, $d['note'] ?? null), t('The URGENT receipt is approved.')],
            };
        } catch (InvalidArgumentException $e) {
            return $fail('purchase-receive', $back, 'urgent', $e);
        }

        return redirect(route('v2.screen', 'purchase-receive').'?id='.$pvId)->with('v2_flash', $done[1]);
    })->whereNumber('pvId')->middleware('module:PROC')->name('urgent');

    // Permintaan Kirim Kekurangan: lintas peran (gudang, PPIC, pembelian), jadi tanpa gerbang modul; hak tiap langkah dijaga ShortageRequestService / QcAuthority.
    Route::prefix('shortage')->name('shortage.')->group(function () use ($fail, $input) {
        Route::post('/', function (Request $r) use ($fail, $input) {
            $d = $r->validate(['wo_material' => ['required', 'string'], 'pv_line_id' => ['required', 'integer'], 'need_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:255']],
                [], ['wo_material' => t('Work Order / Material Issued'), 'pv_line_id' => t('Receipt Line')]);
            try {
                $req = ShortageRequestService::create($d + ['qty_output_good' => $input($r, 'qty_output_good') ?? 0, 'qty_waste_std' => $input($r, 'qty_waste_std')], Auth::user()->login);
            } catch (InvalidArgumentException $e) {
                return $fail('supplier-shortage', ['new' => 1], 'create', $e);
            }

            return redirect(route('v2.screen', 'supplier-shortage').'?id='.$req->id_shortage)->with('v2_flash', tf('Shortage request :no created — the head of the warehouse recounts, then PPIC confirms.', ['no' => raw($req->request_no)]));
        })->name('create');

        Route::post('/lapsed', function () use ($fail) {
            try {
                $n = ShortageRequestService::closeLapsed(Auth::user()->login);
            } catch (InvalidArgumentException $e) {
                return $fail('supplier-shortage', [], 'lapsed', $e);
            }

            return redirect(route('v2.screen', 'supplier-shortage'))->with('v2_flash', tf(':n request(s) closed because the sending window passed.', ['n' => $n]));
        })->name('lapsed');

        Route::post('/{id}/{step}', function (Request $r, int $id, string $step) use ($fail, $input) {
            $req = InvShortageRequest::query()->findOrFail($id);
            $login = Auth::user()->login;
            $note = trim((string) $r->input('note'));

            try {
                match ($step) {
                    'recount' => ShortageRequestService::recount($req, $input($r, 'counted_qty') ?? 0, $input($r, 'recount_weight'), $input($r, 'qty_short') ?? 0, $note, $login),
                    'confirm' => ShortageRequestService::confirm($req, $login),
                    'dismiss' => mb_strlen($note) >= 5 ? ShortageRequestService::dismiss($req, $note, $login) : throw new InvalidArgumentException(t('Give the reason for dismissing (at least 5 characters).')),
                    'send' => ShortageRequestService::send($req, $login),
                    'receive' => ShortageRequestService::receive($req, $note !== '' ? $note : t('The shortfall was delivered.'), $login),
                    'refuse' => ShortageRequestService::refuse($req, $note, $login),
                    'close' => ShortageRequestService::close($req, $login),
                };
            } catch (InvalidArgumentException|RuntimeException $e) {
                return $fail('supplier-shortage', ['id' => $id], 'step', $e);
            }

            return redirect(route('v2.screen', 'supplier-shortage').'?id='.$id)->with('v2_flash', t('Saved.'));
        })->whereNumber('id')->whereIn('step', ['recount', 'confirm', 'dismiss', 'send', 'receive', 'refuse', 'close'])->name('step');
    });
});

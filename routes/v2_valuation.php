<?php

use App\Models\Inventory\InvLandedCost;
use App\Services\Inventory\Valuation\InvPeriodService;
use App\Services\Inventory\Valuation\LandedCostService;
use App\Services\Inventory\Valuation\ObsolescenceService;
use App\Services\Inventory\Valuation\ValuationRefused;
use Devlib\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Inventory > Valuation & Period (v2.01 sisi valuasi dan akuntansi): Landed Cost, Obsolete Stock, tutup / buka periode. Dimuat di dalam grup v2 routes/web.php.
// Route tipis: aturan dan jurnal di App\Services\Inventory\Valuation\*; layar: resources/views/v2/screens/{landed-cost,obsolete-stock,inv-valuation}.blade.php.
// Jurnal yang dibuat: docs/md_for_systems/inventorywarehouse_v201_valuation_journals.md. Kegagalan aturan = ValuationRefused / DomainException (pesan di layar asal).
Route::prefix('v2/inv-valuation')->name('v2.val.')->middleware('module:INV')->group(function () {
    $login = fn () => Auth::user()->login;
    $input = fn (Request $r, string $key) => ($v = $r->input($key)) === null || $v === '' ? null : (float) Locale::input($v);
    $landed = fn (?int $id = null) => route('v2.screen', 'landed-cost').($id ? '?id='.$id : '');
    $fail = fn (string $url, string $key, Throwable $e) => redirect($url)->withErrors([$key => $e->getMessage()]);

    // ------------------------------------------------------------ Landed Cost
    $header = fn (Request $r) => $r->validate(['doc_date' => ['required', 'date'], 'supplier_id' => ['nullable', 'integer'], 'cost_type' => ['required', 'string'],
        'alloc_basis' => ['required', 'string'], 'description' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:255']],
        [], ['doc_date' => t('Date'), 'cost_type' => t('Cost Type'), 'alloc_basis' => t('Share By')]) + ['amount' => $input($r, 'amount') ?? 0.0];

    Route::post('/landed', function (Request $r) use ($header, $login, $landed, $fail) {
        try {
            $doc = app(LandedCostService::class)->create($header($r), $login());
        } catch (ValuationRefused $e) {
            return $fail(route('v2.screen', 'landed-cost').'?new=1', 'landed', $e);
        }

        return redirect($landed($doc->id_landed_cost))->with('v2_flash', t('Saved. Add the goods receipts this cost belongs to.'));
    })->name('landed.create');

    Route::prefix('/landed/{id}')->where(['id' => '[0-9]+'])->group(function () use ($header, $login, $landed, $fail, $input) {
        $doc = fn (int $id) => InvLandedCost::query()->findOrFail($id);
        $run = fn (int $id, string $message, callable $do) => (function () use ($id, $message, $do, $landed, $fail) {
            try {
                $do();
            } catch (ValuationRefused|DomainException $e) {
                return $fail($landed($id), 'landed', $e);
            }

            return redirect($landed($id))->with('v2_flash', $message);
        })();

        Route::post('/save', fn (Request $r, int $id) => $run($id, t('Saved.'), fn () => app(LandedCostService::class)->saveHeader($doc($id), $header($r), $login())))->name('landed.save');
        Route::post('/receipt', fn (Request $r, int $id) => $run($id, t('Receipt lines added.'),
            fn () => app(LandedCostService::class)->addReceipt($doc($id), (int) $r->validate(['pv_id' => ['required', 'integer']], [], ['pv_id' => t('Goods Receipt')])['pv_id'], $login())))->name('landed.receipt');
        Route::post('/line/{line}/remove', fn (int $id, int $line) => $run($id, t('Line removed.'), fn () => app(LandedCostService::class)->removeLine($doc($id), $line)))->whereNumber('line')->name('landed.remove');
        Route::post('/confirm', fn (int $id) => $run($id, t('Confirmed. The journal is posted.'), fn () => app(LandedCostService::class)->confirm($doc($id), $login())))->name('landed.confirm');
        Route::post('/revise', fn (Request $r, int $id) => $run($id, t('Revised. A correction journal was stacked on the original.'),
            fn () => app(LandedCostService::class)->revise($doc($id), (float) ($input($r, 'new_amount') ?? 0), (string) $r->input('reason'), $login())))->name('landed.revise');
        Route::post('/cancel', fn (Request $r, int $id) => $run($id, t('Cancelled. The journal is reversed.'),
            fn () => app(LandedCostService::class)->cancel($doc($id), (string) $r->input('reason'), $login())))->name('landed.cancel');
    });

    Route::post('/landed-close-variance', function (Request $r) use ($login, $fail) {
        $period = (string) $r->validate(['period' => ['required', 'digits:6']], [], ['period' => t('Period')])['period'];
        $home = route('v2.screen', 'inv-valuation');
        try {
            $voucher = app(LandedCostService::class)->closeVariance($period, $login());
        } catch (ValuationRefused|DomainException $e) {
            return $fail($home, 'variance', $e);
        }

        return redirect($home)->with('v2_flash', $voucher ? tf('The landed cost variance of :period was moved to cost of goods sold (:no).', ['period' => raw($period), 'no' => raw($voucher->voucher_number)])
            : t('There is no landed cost variance to close for that period.'));
    })->name('landed.close-variance');

    // ------------------------------------------------------------ Obsolete Stock: provisi hanya diposting atas aksi ini
    Route::post('/obsolete/post', function () use ($login, $fail) {
        $home = route('v2.screen', 'obsolete-stock');
        try {
            $head = app(ObsolescenceService::class)->post($login());
        } catch (ValuationRefused|DomainException $e) {
            return $fail($home, 'provision', $e);
        }

        return redirect($home)->with('v2_flash', tf('Provision posted: :amount.', ['amount' => raw(Locale::money($head->adjustment))]));
    })->name('obsolete.post');

    // ------------------------------------------------------------ Periode inventory
    Route::post('/period/{action}', function (Request $r, string $action) use ($login, $fail) {
        abort_unless(in_array($action, ['close', 'open', 'next'], true), 404);
        $home = route('v2.screen', 'inv-valuation');
        $service = app(InvPeriodService::class);
        try {
            if ($action === 'next') {
                $service->createNext($login());
            } else {
                $code = (string) $r->validate(['period' => ['required', 'digits:6']], [], ['period' => t('Period')])['period'];
                $action === 'close' ? $service->close($code, $login()) : $service->open($code, $login());
            }
        } catch (ValuationRefused|DomainException $e) {
            return $fail($home, 'period', $e);
        }

        return redirect($home)->with('v2_flash', t('Saved.'));
    })->name('period');
});

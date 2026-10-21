<?php

/*
 * REMARK
 * Tujuan       : Route aksi layar ProCost v2: Costing Hub per inquiry (procost) dan Instant (procost-instant). Satu rute simpan per layar; tombol form mengirim action (save, hitung, submit, publish, quotation, tambah / hapus part, coating, pilih layout ProDev, Book, ...). Isian form dikirim lebih dulu ke service, baru aksi dijalankan.
 * Sumber hulu  : App\Services\Costing\CostingEntryService dan InstantCostingService (aturan), App\Support\V2\Data\ProCost dan ProCostInstant (menerapkan isian form per field yang berubah).
 * Input        : POST form layar (header, tier[id][..], part[id][..], coating[id][..], part_no, tier_id, action).
 * Output       : Redirect kembali ke layar (inquiry / id, part, tier) dengan flash v2_flash, procost_notices, atau errors.
 * Dipakai oleh : routes/web.php (dimuat di baris yang sama dengan v2_finance.php supaya nomor baris web.php yang dikutip kartu TG tidak bergeser); layar procost dan procost-instant.
 * Status       : Aktif. Dibuat 2026-10-21 (T10 tahap 3, Jo): menggantikan halaman v1 pages::inquiry.costing dan costing-instant.
 */

use App\Models\Sdi\IqpCosting;
use App\Services\Costing\CostingEntryService as Entry;
use App\Services\Costing\InstantCostingService as Instant;
use App\Support\V2\Data\ProCost;
use App\Support\V2\Data\ProCostInstant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v2/procost')->name('v2.procost.')->middleware('module:COSTING')->group(function () {
    $screen = fn (Request $r, int $inquiry, ?int $part = null) => route('v2.screen', 'procost').'?inquiry='.$inquiry.'&part='.($part ?? max(1, (int) $r->input('part_no', 1))).($r->filled('tier_id') ? '&tier='.(int) $r->input('tier_id') : '');

    Route::post('/{inquiry}/save', function (Request $r, int $inquiry) use ($screen) {
        $c = IqpCosting::where('inquiry_id', $inquiry)->firstOrFail();
        $action = (string) $r->input('action', 'save');
        $partId = (int) $r->input('part_id', 0);
        $flash = [];
        $errors = [];

        if (Entry::editable($c)) {
            $notices = ProCost::save($c, $r->all());
            if ($notices) {
                $flash['procost_notices'] = $notices;
            }
        }
        $c->refresh();
        $part = null;

        match (true) {
            $action === 'hitung' => ($e = Entry::hitung($c)) ? $errors['engine'] = $e : null,
            $action === 'submit' => Entry::submitToDireksi($c),
            $action === 'publish' => Entry::publish($c),
            $action === 'add_part' => $part = (int) $c->parts()->count() + (Entry::addPart($c) ? 1 : 0),
            $action === 'add_part_inq' && $r->filled('new_inq_part') => ($e = Entry::addPartFromInquiry($c, (int) $r->input('new_inq_part'))) ? $errors['engine'] = $e : $part = (int) $c->parts()->count(),
            $action === 'remove_part' => Entry::removePart($c, $partId),
            $action === 'toggle_source' => Entry::toggleDataSource($c, $partId),
            $action === 'pick_layout' && $r->filled('layout_group') => ($e = Entry::pickLayoutGroup($c, $partId, (int) $r->input('layout_group'))) ? $errors['layout'] = $e : null,
            $action === 'add_coating' => Entry::addCoating($c, $partId),
            str_starts_with($action, 'remove_coating_') => Entry::removeCoating($c, (int) substr($action, 15)),
            $action === 'book' => ($e = Entry::book($c)) ? $errors['book'] = $e : null,
            $action === 'release_book' => Entry::releaseBook($c),
            default => null,
        };

        if ($action === 'quotation') {
            $q = Entry::addToQuotation($c);
            if ($q) {
                return redirect(route('v2.screen', 'quotation').'?id='.$q->id_quotation);
            }
        }

        $back = redirect($screen($r, $inquiry, $part))->with($flash);
        if ($action === 'save') {
            $back->with('v2_flash', t('Saved.'));
        }

        return $errors ? $back->withErrors($errors) : $back;
    })->whereNumber('inquiry')->name('save');

    // "Memang tidak dipakai": isian yang dikosongkan sengaja menjadi 0 (tidak dipulihkan lagi).
    Route::post('/{inquiry}/accept-zero', function (Request $r, int $inquiry) use ($screen) {
        Entry::acceptZero(IqpCosting::where('inquiry_id', $inquiry)->firstOrFail(), ['part' => $r->input('part') ?: null, 'tier' => $r->input('tier') ?: null, 'field' => (string) $r->input('field')]);

        return redirect($screen($r, $inquiry));
    })->whereNumber('inquiry')->name('accept-zero');
});

Route::prefix('v2/procost-instant')->name('v2.procost-instant.')->middleware('module:COSTING')->group(function () {
    $screen = fn (Request $r, int $id, ?int $part = null) => route('v2.screen', 'procost-instant').'?id='.$id.'&part='.($part ?? max(1, (int) $r->input('part_no', 1))).($r->filled('tab') ? '&tab='.e($r->input('tab')) : '');

    Route::post('/{id}/save', function (Request $r, int $id) use ($screen) {
        $c = Instant::open($id);
        $action = (string) $r->input('action', 'save');
        $errors = [];
        $part = null;

        if ($action === 'delete') {
            return Instant::delete($c) ? redirect(route('v2.screen', 'sales-procost-instant'))->with('v2_flash', t('Costing deleted.'))
                : redirect($screen($r, $id))->withErrors(['doc' => t('This document is already locked.')]);
        }

        $err = Instant::readOnly($c) ? '' : ProCostInstant::save($c, $r->all());
        $c->refresh();

        switch (true) {
            case $action === 'save': case $action === 'recalc': $err = Instant::recalc($c) ?: $err;
                break;
            case $action === 'add_part': $part = $c->parts()->count() + (Instant::addPart($c) ? 1 : 0);
                break;
            case $action === 'remove_part': $err = Instant::removePart($c, (int) $r->input('part_id')) ?: $err;
                $part = max(1, (int) $r->input('part_no', 1) - 1);
                break;
            case $action === 'add_coating': $err = Instant::addCoating($c, (int) $r->input('part_id')) ?: $err;
                break;
            case str_starts_with($action, 'remove_coating_'): $err = Instant::removeCoating($c, (int) $r->input('part_id'), (int) substr($action, 15)) ?: $err;
                break;
            case $action === 'confirm': $err = Instant::confirm($c) ?: $err;
                break;
            case $action === 'cancel': Instant::cancel($c);
                break;
            case $action === 'reverse':
                try {
                    return redirect(route('v2.screen', 'procost').'?inquiry='.Instant::reverse($c))->with('v2_flash', t('Reversed: a new Inquiry and a draft ProDev were created; this document is now Integrated.'));
                } catch (Throwable $e) {
                    $err = $e->getMessage();
                }
                break;
        }

        $back = redirect($screen($r, $id, $part));
        if ($action === 'save' && ! $err) {
            $back->with('v2_flash', t('Saved and recalculated.'));
        }

        return $err ? $back->withErrors(['engine' => $err]) : $back;
    })->whereNumber('id')->name('save');
});

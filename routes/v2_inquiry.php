<?php

/*
 * REMARK
 * Tujuan       : Route tambahan layar Inquiry Baru v2 (inquiry-new): saran per part (hanya membaca) dan hapus artwork tersimpan saat DRAFT. Route simpan, end, artwork, customer tetap di routes/v2.php.
 * Sumber hulu  : App\Services\Sales\InquirySuggestionService (saran), App\Services\Sales\InquiryWriter::removeArtwork (hapus artwork, ditolak bila Inquiry sudah Confirm).
 * Input        : POST type_code, artwork (berkas sementara) atau part_id (artwork tersimpan); POST part_id (+ inner) untuk hapus artwork.
 * Output       : JSON {suggestions: [...]} dan JSON {message}.
 * Dipakai oleh : routes/web.php (dimuat di baris yang sama dengan v2_finance.php supaya nomor baris yang dikutip kartu TG tidak bergeser); inquiry-form.js (x-mp::sales.inquiry-form).
 * Status       : Aktif. Dibuat 2026-10-21 (T10 tahap 4, Jo): padanan fitur form v1 pages::inquiry.form yang belum ada di v2.
 */

use App\Models\Sdi\IqpInquiry;
use App\Models\Sdi\IqpPart;
use App\Services\Sales\InquirySuggestionService;
use App\Services\Sales\InquiryWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::prefix('v2/inquiry')->name('v2.inquiry.')->group(function () {
    Route::post('/{id}/artwork/remove', function (Request $r, int $id) {
        abort_unless(Auth::user()->canAccessModule('SALES'), 403);
        $part = IqpInquiry::findOrFail($id)->parts()->findOrFail((int) $r->validate(['part_id' => ['required', 'integer']])['part_id']);
        (new InquiryWriter(Auth::user()->login))->removeArtwork($part, $r->boolean('inner'));

        return response()->json(['message' => tf('Artwork of :part removed.', ['part' => $part->part_name])]);
    })->whereNumber('id')->name('artwork.remove');

    // Saran untuk satu part (warna/ukuran dari artwork, bahan/finishing dari histori); hanya membaca, tidak menyimpan apa pun.
    Route::post('/suggest', function (Request $r) {
        abort_unless(Auth::user()->canAccessModule('SALES'), 403);
        $r->validate(['type_code' => ['nullable', 'string', 'max:50'], 'artwork' => ['nullable', 'image', 'mimes:jpg,jpeg,png'], 'part_id' => ['nullable', 'integer']]);
        $service = new InquirySuggestionService;
        $file = $r->file('artwork')?->getRealPath()
            ?? ($r->filled('part_id') ? $service->storedArtwork(IqpPart::whereKey((int) $r->input('part_id'))->value('artwork_path')) : null);

        return response()->json(['suggestions' => $service->forPart((string) $r->input('type_code', ''), $file)]);
    })->name('suggest');
});

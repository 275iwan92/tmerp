<?php

/*
 * REMARK
 * Tujuan       : Engine harga beli Single Face per supplier: harga per lembar = index (per m2) x panjang (m) x lebar (m) / (1 + PPN/100).
 * Sumber hulu  : sup_single_face_price (layar Supplier Single Face Price), tarif PPN pada tanggal dokumen (TaxRate), nama bahan di Master Raw Material (substance, flute, ukuran).
 * Input        : MatRaw, id supplier, tanggal dokumen.
 * Output       : Harga di luar PPN per satuan beli (LBR / PCS per lembar, RIM = 500 lembar) atau null bila data kurang; engine menyarankan, petugas memutuskan.
 * Dipakai oleh : PaperPriceEngine::priceFor (PR dan PO), tes PaperPriceEngineTest.
 * Status       : Aktif. Rumus Jo 2026-10-17; tidak menulis apa pun; substance, flute, ukuran dibaca dari nama bahan (CM atau MM).
 */

namespace App\Services\Pur;

use App\Models\Master\MatRaw;
use App\Models\Master\SupSingleFacePrice;
use Carbon\CarbonInterface;

final class SingleFacePriceEngine
{
    private const SHEETS_PER_REAM = 500;

    // Bahan Single Face = nama memuat SF, S FACE, atau SINGLE FACE sebagai kata utuh.
    public function matches(MatRaw $m): bool
    {
        return (bool) preg_match('/\b(SF|S\s*FACE|SINGLE\s*FACE)\b/i', (string) $m->material_name);
    }

    // Harga di luar PPN per satuan beli: LBR / PCS / kosong = per lembar, RIM = 500 lembar; selain itu null.
    public function priceFor(MatRaw $m, ?int $supplierId, ?CarbonInterface $on = null): ?float
    {
        $uom = strtoupper((string) $m->uom_purchase);
        $size = PaperSpec::sizeM((string) $m->material_name);
        $row = $supplierId && $size ? $this->row($m, $supplierId, $on) : null;
        $div = $row ? app(PaperPriceEngine::class)->divisor($row->price_basis, $on) : null;
        if (! $row || ! $div || ! in_array($uom, ['', 'LBR', 'PCS', 'RIM'], true)) {
            return null;
        }
        $sheet = (float) $row->index_per_m2 * $size[0] * $size[1] / $div;

        return round($uom === 'RIM' ? $sheet * self::SHEETS_PER_REAM : $sheet, 4);
    }

    // Baris harga yang berlaku: aktif, tanggal berlaku <= tanggal dokumen, substance dan flute sama dengan nama bahan; yang tanggalnya paling baru.
    public function row(MatRaw $m, int $supplierId, ?CarbonInterface $on = null): ?SupSingleFacePrice
    {
        $key = PaperSpec::substanceKey((string) $m->material_name);
        $flute = PaperSpec::fluteKey((string) $m->material_name);
        if ($key === '' || $flute === '') {
            return null;
        }

        return SupSingleFacePrice::query()->where('supplier_id', $supplierId)->where('activate', true)
            ->whereDate('effective_date', '<=', ($on ?? now())->toDateString())->orderByDesc('effective_date')->orderByDesc('id_sf_price')->get()
            ->first(fn (SupSingleFacePrice $r) => PaperSpec::substanceKey($r->substance_name) === $key && PaperSpec::fluteKey($r->flute_type) === $flute);
    }
}

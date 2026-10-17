<?php

/*
 * REMARK
 * Tujuan       : Engine harga beli duplex per supplier, dua metode dari Excel Jo (sheet INDEX): Reference sheet = P x L / (79 x 109) x index x APKey; Weight = gsm x P x L / 20000 x index x APKey; dibagi (1 + PPN/100) bila harga sudah termasuk PPN.
 * Sumber hulu  : sup_duplex_price (layar Supplier Duplex Price), tarif PPN pada tanggal dokumen (TaxRate), Master Raw Material (gramasi, ukuran cm, satuan beli; bila kosong dibaca dari nama oleh PaperSpec).
 * Input        : MatRaw, id supplier, tanggal dokumen.
 * Output       : Harga di luar PPN per satuan beli (RIM, LBR = rim / 500) atau null bila data kurang atau grade ambigu; engine menyarankan, petugas memutuskan.
 * Dipakai oleh : PaperPriceEngine::priceFor (PR dan PO), tes DuplexPriceEngineTest.
 * Status       : Aktif. APKey dalam persen: 500 = dikali 5,00 (PLUS di Excel); potongan roll belum dihitung; tidak menulis apa pun.
 */

namespace App\Services\Pur;

use App\Models\Master\MatRaw;
use App\Models\Master\SupDuplexPrice;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class DuplexPriceEngine
{
    public const REF_SHEET = 'REF_REAM';

    public const WEIGHT = 'WEIGHT';

    private const SHEETS_PER_REAM = 500;

    public function matches(MatRaw $m): bool
    {
        return (bool) preg_match('/\bDUPLEX\b/i', (string) $m->material_name);
    }

    // Harga 1 rim sebelum pembagi PPN dari satu baris harga (rumus Excel), atau null bila ukuran / gramasi kurang.
    public function reamPrice(SupDuplexPrice $r, float $gsm, float $lengthCm, float $widthCm): ?float
    {
        if ($gsm <= 0 || $lengthCm <= 0 || $widthCm <= 0) {
            return null;
        }
        $base = $r->formula === self::WEIGHT ? $gsm * $lengthCm * $widthCm / 20000 : $lengthCm * $widthCm / (79 * 109);

        return $base * (float) $r->index_value * (float) $r->apkey_pct / 100;
    }

    // Harga di luar PPN per satuan beli: RIM atau LBR (rim / 500); selain itu null.
    public function priceFor(MatRaw $m, ?int $supplierId, ?CarbonInterface $on = null): ?float
    {
        $uom = strtoupper((string) $m->uom_purchase);
        $size = PaperSpec::sizeCm($m);
        $gsm = PaperSpec::gsm($m);
        $row = $supplierId && $size && in_array($uom, ['RIM', 'LBR'], true) ? $this->row($m, $supplierId, $gsm, $on) : null;
        $div = $row ? app(PaperPriceEngine::class)->divisor($row->price_basis, $on) : null;
        $ream = $row && $div ? $this->reamPrice($row, $gsm, $size[0], $size[1]) : null;
        if ($ream === null) {
            return null;
        }
        $ream /= $div;

        return round($uom === 'LBR' ? $ream / self::SHEETS_PER_REAM : $ream, 4);
    }

    // Baris yang berlaku: aktif, tanggal berlaku <= tanggal dokumen (yang terbaru per gsm_from + grade), gsm bahan di dalam rentang.
    // Grade yang tertulis di nama bahan didahulukan, lalu baris tanpa grade; bila hanya ada baris bergrade dan lebih dari satu grade cocok, hasilnya null (petugas memilih).
    public function row(MatRaw $m, int $supplierId, float $gsm, ?CarbonInterface $on = null): ?SupDuplexPrice
    {
        if ($gsm <= 0) {
            return null;
        }
        $rows = SupDuplexPrice::query()->where('supplier_id', $supplierId)->where('activate', true)
            ->whereDate('effective_date', '<=', ($on ?? now())->toDateString())->where('gsm_from', '<=', $gsm)->where('gsm_to', '>=', $gsm)
            ->orderByDesc('effective_date')->orderByDesc('id_duplex_price')->get();
        $latest = $rows->unique(fn (SupDuplexPrice $r) => strtoupper((string) $r->grade).'|'.$r->gsm_from);
        $text = strtoupper((string) $m->material_name.' '.(string) $m->brand);
        $named = $latest->filter(fn (SupDuplexPrice $r) => $r->grade && preg_match('/\b'.preg_quote(strtoupper($r->grade), '/').'\b/', $text));
        $pick = fn (Collection $c) => $c->count() === 1 ? $c->first() : null;
        if ($named->isNotEmpty()) {
            return $pick($named->unique('grade'));
        }
        $open = $latest->filter(fn (SupDuplexPrice $r) => ! $r->grade);
        if ($open->isNotEmpty()) {
            return $open->first();
        }

        return $pick($latest->unique('grade'));
    }
}

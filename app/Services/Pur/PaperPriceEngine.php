<?php

/*
 * REMARK
 * Tujuan       : Engine harga kertas per supplier: harga per rim (ukuran standar atau potong dari roll), jumlah rim dari roll, harga per satuan beli bahan.
 * Sumber hulu  : Konstanta sup_paper_price (layar Supplier Paper Price), tarif PPN pada tanggal dokumen (TaxRate, layar Tax Rate), Master Raw Material (gramasi, ukuran cm, satuan beli).
 * Input        : MatRaw, id supplier, tanggal dokumen; atau SupPaperPrice + panjang/lebar (m) + gramasi + tanggal.
 * Output       : Harga di luar PPN per satuan beli (RIM, LBR, KG) atau null bila data kurang; engine menyarankan, petugas memutuskan.
 * Dipakai oleh : Purchase Requisition (isi harga taksiran), PoBuilderService (harga baris PO), tes PaperPriceEngineTest.
 * Status       : Aktif. Rumus Jo 2026-10-17: rim = P x L x (gsm/2) kg; harga rim = (harga/kg x berat rim) / (1 + PPN/100); tidak menulis apa pun.
 */

namespace App\Services\Pur;

use App\Models\Master\MatGrammage;
use App\Models\Master\MatRaw;
use App\Models\Master\SupPaperPrice;
use App\Support\Finance\TaxRate;
use Carbon\CarbonInterface;

final class PaperPriceEngine
{
    private const SHEETS_PER_REAM = 500;

    // Pembagi PPN = 1 + tarif PPN pada tanggal dokumen / 100 (layar Tax Rate: 10% sebelum 01-Apr-2022, 11% sesudahnya); harga Excl PPN tidak dibagi.
    public function divisor(string $basis, ?CarbonInterface $on = null): ?float
    {
        if ($basis === 'EXCL') {
            return 1.0;
        }
        $ppn = TaxRate::ppn($on);

        return $ppn > 0 ? 1 + $ppn / 100 : null;
    }

    public function rimWeightKg(float $lengthM, float $widthM, float $gsm): float
    {
        return $lengthM * $widthM * ($gsm / 2);
    }

    // Rim yang didapat dari satu roll: tonase (kg) / panjang / lebar / (gsm / 2).
    public function reamsFromRoll(float $kg, float $lengthM, float $widthM, float $gsm): float
    {
        $w = $this->rimWeightKg($lengthM, $widthM, $gsm);

        return $w > 0 ? $kg / $w : 0.0;
    }

    // Harga 1 rim di luar PPN. $cutFromRoll = beli roll lalu dipotong (harga roll + biaya potong), kalau tidak = harga ream ukuran standar.
    public function rimPrice(SupPaperPrice $p, float $lengthM, float $widthM, float $gsm, bool $cutFromRoll, ?CarbonInterface $on = null): ?float
    {
        $perKg = $cutFromRoll ? $this->rollPerKg($p) + (float) $p->cut_cost_per_kg : (float) $p->sheet_price_per_kg;
        $div = $this->divisor($p->price_basis, $on);
        if ($perKg <= 0 || ! $div || $lengthM <= 0 || $widthM <= 0 || $gsm <= 0) {
            return null;
        }

        return round($perKg * $this->rimWeightKg($lengthM, $widthM, $gsm) / $div, 4);
    }

    // Harga di luar PPN per satuan beli bahan: RIM / LBR (ukuran standar) atau KG (harga roll). Null = tidak ada harga untuk bahan ini.
    public function priceFor(MatRaw $m, ?int $supplierId, ?CarbonInterface $on = null): ?float
    {
        $sf = app(SingleFacePriceEngine::class);
        if ($sf->matches($m)) {
            return $sf->priceFor($m, $supplierId, $on);
        }
        $p = $supplierId ? $this->row($m, $supplierId, $on) : null;
        $uom = strtoupper((string) $m->uom_purchase);
        if (! $p) {
            return null;
        }
        if ($uom === 'KG') {
            $div = $this->divisor($p->price_basis, $on);

            return $div && $this->rollPerKg($p) > 0 ? round($this->rollPerKg($p) / $div, 4) : null;
        }
        $rim = $this->rimPrice($p, (float) $m->cm_length / 100, (float) $m->cm_width / 100, (float) $m->gram_weight, false, $on);

        return match ($uom) {
            'RIM' => $rim,
            'LBR' => $rim === null ? null : round($rim / self::SHEETS_PER_REAM, 4),
            default => null,
        };
    }

    // Baris harga yang berlaku: aktif, tanggal berlaku <= tanggal dokumen, jenis kertas tampak di nama/kategori bahan.
    // Gramasi yang cocok didahulukan, lalu baris gramasi kosong; di antara keduanya yang tanggalnya paling baru.
    public function row(MatRaw $m, int $supplierId, ?CarbonInterface $on = null): ?SupPaperPrice
    {
        $text = strtoupper(implode(' ', array_filter([$m->material_name, $m->category1, $m->category2, $m->category3, $m->category4, $m->category5])));
        $rows = SupPaperPrice::query()->where('supplier_id', $supplierId)->where('activate', true)
            ->whereDate('effective_date', '<=', ($on ?? now())->toDateString())->orderByDesc('effective_date')->orderByDesc('id_paper_price')->get()
            ->filter(fn (SupPaperPrice $r) => str_contains($text, strtoupper($r->paper_kind)));
        $grammage = $rows->pluck('grammage_id')->filter()->unique()->mapWithKeys(fn ($id) => [$id => (float) MatGrammage::query()->whereKey($id)->value('grammage_value')]);

        return $rows->first(fn (SupPaperPrice $r) => $r->grammage_id && abs($grammage[$r->grammage_id] - (float) $m->gram_weight) < 0.005)
            ?? $rows->first(fn (SupPaperPrice $r) => ! $r->grammage_id);
    }

    private function rollPerKg(SupPaperPrice $p): float
    {
        return (float) $p->roll_price / ($p->roll_price_unit === 'TON' ? 1000 : 1);
    }
}
